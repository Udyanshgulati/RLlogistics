<?php

declare(strict_types=1);

namespace Commerce\Core\Tests\Catalog;

use Commerce\Core\Catalog\CatalogException;
use Commerce\Core\Catalog\SkuPolicy;
use Commerce\Core\Inventory\InventoryCsv;
use Commerce\Core\Inventory\InventoryView;
use Commerce\Core\Tests\Support\LedgerTestCase;

final class CatalogTest extends LedgerTestCase
{
    public function testSizeRunGeneratesSixSkusEachWithItsOwnStockRecord(): void
    {
        $ids = $this->catalog->createProduct(1, 'mi-jer-001', 'Match Jersey', 'Jerseys', SkuPolicy::APPAREL_SIZES, 'user:1', 'apparel_size');
        $this->assertSame(['MI-JER-001-S', 'MI-JER-001-M', 'MI-JER-001-L', 'MI-JER-001-XL', 'MI-JER-001-XXL', 'MI-JER-001-XXXL'], array_keys($ids));
        $this->assertSame(6, (int) $this->db->value('SELECT COUNT(*) FROM stock_levels'));
        $this->assertSame(0, $this->ledger->level($ids['MI-JER-001-M'])->onHand);
    }

    public function testDuplicateAndMalformedSkusAreRejected(): void
    {
        $this->catalog->createProduct(1, 'MI-JER-001', 'Match Jersey', null, ['S', 'M'], 'u');
        $e = $this->assertThrows(CatalogException::class, fn () => $this->catalog->createProduct(1, 'MI-JER-001', 'Other', null, ['S'], 'u'));
        $this->assertContains('SKU MI-JER-001-S is already in use.', $e->problems);
        $this->assertThrows(CatalogException::class, fn () => $this->catalog->createProduct(1, 'MI JER 1', 'Bad', null, [], 'u'), 'format');
        $this->assertThrows(CatalogException::class, fn () => $this->catalog->createProduct(1, 'SS-TEE-001', 'Wrong brand', null, [], 'u'), 'start with MI-');
        $this->assertThrows(\InvalidArgumentException::class, fn () => (new SkuPolicy())->generate('MI-TEE-001', ['M', 'm']), 'twice');
    }

    public function testSizesAreConfigurableAndNonApparelProductsHaveNoVariations(): void
    {
        $policy = new SkuPolicy(sizeRun: ['XS', 'S']);
        $this->assertSame(['XS', 'S'], $policy->sizeRun());
        $ids = $this->catalog->createProduct(3, 'SCH-MUG-001', 'Mug', 'Home', [], 'u');
        $this->assertSame(['SCH-MUG-001'], array_keys($ids));
        $this->assertSame('none', $this->db->value("SELECT variant_scheme FROM products WHERE code = 'SCH-MUG-001'"));
    }

    public function testSharingAcrossBrandsNeedsExplicitConfirmation(): void
    {
        $item = $this->item('MI-TEE-001', 5);
        $own = $this->catalog->recordListing(1, 10, 0, 'MI-TEE-001', 'Black tee');
        $other = $this->catalog->recordListing(3, 20, 0, 'MI-TEE-001', 'Black tee');
        $this->catalog->mapListing($own, $item, 'u');
        $this->assertSame('exclusive', $this->catalog->sharing($item));

        $e = $this->assertThrows(CatalogException::class, fn () => $this->catalog->mapListing($other, $item, 'u'));
        $this->assertSame('shared', $e->needsConfirmation);
        $this->catalog->mapListing($other, $item, 'u', confirmShared: true);
        $this->assertSame('shared', $this->catalog->sharing($item));
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM audit_log WHERE action = 'listing.map' AND details LIKE '%\"shared\":true%'"));
    }

    public function testNeverMapsOnNamesAndAsksAboutSkuMismatch(): void
    {
        $item = $this->item('MI-TEE-001', 5);
        $this->catalog->recordListing(1, 11, 0, null, 'Test product MI-TEE-001'); // same name, no SKU
        $this->assertSame([], $this->catalog->suggestions(), 'a matching name is not a match');
        $this->assertSame('unmapped', $this->catalog->sharing($item));

        $odd = $this->catalog->recordListing(1, 12, 0, 'TEE-BLK-M', 'Tee');
        $e = $this->assertThrows(CatalogException::class, fn () => $this->catalog->mapListing($odd, $item, 'u'));
        $this->assertSame('sku_mismatch', $e->needsConfirmation);

        $exact = $this->catalog->recordListing(2, 13, 0, 'MI-TEE-001', 'Tee');
        $this->assertCount(1, $this->catalog->suggestions());
        $this->assertSame('unmapped', $this->db->value('SELECT status FROM listings WHERE id = ?', [$exact]), 'suggested, not mapped');
    }

    public function testRemappingRequiresUnlinkingFirst(): void
    {
        $a = $this->item('MI-TEE-001', 1);
        $b = $this->item('MI-TEE-002', 1);
        $l = $this->list(1, $a, 30);
        $this->assertThrows(CatalogException::class, fn () => $this->catalog->mapListing($l, $b, 'u'), 'Unlink it first');
        $this->catalog->unmapListing($l, 'u', 'Linked to the wrong size');
        $this->catalog->mapListing($l, $b, 'u', confirmShared: true);
        $this->assertSame($b, (int) $this->db->value('SELECT inventory_item_id FROM listings WHERE id = ?', [$l]));
    }

    public function testCsvImportValidatesEveryRowBeforeWriting(): void
    {
        $a = $this->item('MI-TEE-001', 10);
        $this->item('MI-TEE-002', 0);
        $this->ledger->reserve(1, 'o', [$a => 4], 'k', 900, 3600);
        $csv = "sku,counted_quantity,received_quantity,reorder_level\n"
            . "MI-TEE-001,12,,5\n"     // ok: count 10 -> 12
            . "mi-tee-002,,6,\n"       // ok: lower case is normalised
            . "MI-TEE-404,3,,\n"       // unknown SKU
            . "MI-TEE-001,1,,\n"       // duplicate
            . "MI-TEE-002,1,2,\n"      // both columns
            . "MI-TEE-002,-1,,\n";     // negative
        $csvTool = new InventoryCsv($this->db, $this->ledger);
        $p = $csvTool->preview($csv);
        $this->assertSame(2, $p['valid']);
        $this->assertSame(4, $p['invalid']);
        $this->assertSame([null, null, 'No product has this SKU. Add the product first.'], array_slice(array_column($p['rows'], 'error'), 0, 3));
        $this->assertSame(10, $this->ledger->level($a)->onHand, 'preview writes nothing');

        $this->assertSame(2, $csvTool->apply($p, 'user:1', 'count.csv'));
        $this->assertSame(12, $this->ledger->level($a)->onHand);
        $this->assertSame(5, (int) $this->db->value('SELECT reorder_level FROM inventory_items WHERE id = ?', [$a]));

        $bad = $csvTool->preview("sku,counted_quantity\nMI-TEE-001,2\n");
        $this->assertStringContains('4 units are reserved', $bad['rows'][0]['error']);
        $this->assertStringContains('header', $csvTool->preview("hello\n")['error']);
    }

    public function testInventoryViewAndExportAgree(): void
    {
        $a = $this->item('MI-TEE-001', 3, reorder: 5);
        $this->item('MI-TEE-002', 0);
        $this->list(1, $a, 40);
        $this->list(2, $a, 41);
        $view = new InventoryView($this->db);
        $this->assertSame(['low', 'out'], array_column($view->list(['sort' => 'sku'])['rows'], 'status'));
        $this->assertSame(1, $view->list(['status' => 'out'])['total']);
        $this->assertSame(1, $view->list(['brand' => 2])['total'], 'Significant Sports sees the shared item it sells');
        $this->assertSame(0, $view->list([], brandScope: [3])['total'], 'Schermo sees nothing it neither owns nor sells');
        $t = $view->totals();
        $this->assertSame(['skus' => 2, 'on_hand' => 3, 'available' => 3, 'out_of_stock' => 1, 'low_stock' => 1], $t);

        $csv = InventoryCsv::export($view->list(['sort' => 'sku'])['rows'], [1 => 'Milan Icons', 2 => 'Significant Sports']);
        $this->assertStringContains('MI-TEE-001,"Test product MI-TEE-001",,"Milan Icons | Significant Sports",shared,3,0,0,0,3,0,5,low', $csv);
        $this->assertSame("'=HYPERLINK(1)", InventoryCsv::safeCell('=HYPERLINK(1)'));
        $this->assertSame('-5', InventoryCsv::safeCell(-5));
    }
}
