<?php

declare(strict_types=1);

namespace Commerce\Core\Tests\Inventory;

use Commerce\Core\Inventory\InventoryView;
use Commerce\Core\Inventory\LedgerException;
use Commerce\Core\Inventory\Purchasing;
use Commerce\Core\Support\Audit;
use Commerce\Core\Tests\Support\LedgerTestCase;

final class PurchasingTest extends LedgerTestCase
{
    public function testIncomingIsShownButOnlyReceiptsBecomeStock(): void
    {
        $item = $this->item('SCH-TEE-001', 5, brandId: 3, reorder: 10);
        $p = new Purchasing($this->db, $this->ledger, new Audit($this->db, $this->clock), $this->clock);
        $po = $p->create($p->supplier('Test Supplier', null, null, 'u'), [$item => 20], '2026-11-20', 'u');
        $line = (int) $this->db->value('SELECT id FROM purchase_order_lines WHERE purchase_order_id = ?', [$po]);
        $this->assertThrows(LedgerException::class, fn () => $p->receive($line, 1, 0, 0, 'u'), 'ordered');
        $p->markOrdered($po, 'u');

        $view = new InventoryView($this->db);
        $this->assertSame(20, $view->one($item)['incoming']);
        $this->assertSame(5, $view->one($item)['available'], 'incoming is not available');

        $p->receive($line, 12, 1, 0, 'user:wh');
        $this->assertSame('partially_received', $this->db->value('SELECT status FROM purchase_orders WHERE id = ?', [$po]));
        $this->assertSame(7, $view->one($item)['incoming']);
        $this->assertSame(17, $view->one($item)['available'], 'damaged units are held back');
        $this->assertThrows(LedgerException::class, fn () => $p->receive($line, 8, 0, 0, 'u'), 'Only 7');
        $p->receive($line, 5, 0, 2, 'user:wh');
        $this->assertSame('received', $this->db->value('SELECT status FROM purchase_orders WHERE id = ?', [$po]));
        $this->assertSame(0, $view->one($item)['incoming']);
        $this->assertSame(23, $this->ledger->level($item)->onHand);
    }
}
