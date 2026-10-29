<?php

declare(strict_types=1);

namespace Commerce\Core\Tests\Support;

use Commerce\Core\Catalog\Catalog;
use Commerce\Core\Catalog\SkuPolicy;
use Commerce\Core\Db\Database;
use Commerce\Core\Inventory\Adjustment;
use Commerce\Core\Inventory\Ledger;
use Commerce\Core\Inventory\Reconciler;
use Commerce\Core\Support\Audit;
use Commerce\Core\Support\FixedClock;

abstract class LedgerTestCase extends TestCase
{
    protected Database $db;
    protected FixedClock $clock;
    protected Ledger $ledger;
    protected Catalog $catalog;

    public function setUp(): void
    {
        $this->db = TestDb::fresh();
        $this->clock = new FixedClock();
        $this->ledger = new Ledger($this->db, $this->clock);
        $this->catalog = new Catalog($this->db, $this->ledger, new SkuPolicy(), new Audit($this->db, $this->clock), $this->clock);
    }

    public function tearDown(): void
    {
        // Every test ends with a self-consistent ledger, whatever it did.
        if (isset($this->db)) {
            $this->assertSame([], (new Reconciler($this->db))->problems(), 'Ledger reconciliation');
        }
    }

    /** A single stock item with on-hand stock. */
    protected function item(string $code = 'MI-TEE-001', int $qty = 0, int $brandId = 1, int $reorder = 0): int
    {
        $ids = $this->catalog->createProduct($brandId, $code, 'Test product ' . $code, 'Apparel', [], 'test');
        $id = $ids[$code];
        if ($qty > 0) {
            $this->ledger->adjust($id, Adjustment::Received, $qty, 'test');
        }
        if ($reorder > 0) {
            $this->db->run('UPDATE inventory_items SET reorder_level = ? WHERE id = ?', [$reorder, $id]);
        }
        return $id;
    }

    /** Maps a new storefront listing in $storeId to $itemId (confirming cross-brand sharing). */
    protected function list(int $storeId, int $itemId, int $wcProductId): int
    {
        $listing = $this->catalog->recordListing($storeId, $wcProductId, 0, null, 'Listing');
        $this->catalog->mapListing($listing, $itemId, 'test', confirmShared: true);
        return $listing;
    }

    protected function available(int $itemId): int
    {
        return $this->ledger->level($itemId)->available();
    }
}
