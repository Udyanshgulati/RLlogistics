<?php

declare(strict_types=1);

namespace Commerce\Core\Inventory;

use Commerce\Core\Db\Database;
use Commerce\Core\Support\Audit;
use Commerce\Core\Support\Clock;

/**
 * Suppliers and purchase orders, kept deliberately small: no costing, invoicing or approvals.
 * Ordered units show as "Incoming" but are never available until a receipt is recorded.
 */
final class Purchasing
{
    public function __construct(private readonly Database $db, private readonly Ledger $ledger, private readonly Audit $audit, private readonly Clock $clock)
    {
    }

    public function supplier(string $name, ?string $email, ?string $phone, string $actor): int
    {
        if (trim($name) === '') {
            throw new LedgerException('Enter the supplier name.');
        }
        $existing = $this->db->value('SELECT id FROM suppliers WHERE name = ?', [trim($name)]);
        if ($existing) {
            return (int) $existing;
        }
        $id = $this->db->insert('INSERT INTO suppliers (name, contact_email, contact_phone, created_at) VALUES (?, ?, ?, ?)', [trim($name), $email ?: null, $phone ?: null, $this->clock->now()]);
        $this->audit->log($actor, 'supplier.create', 'supplier', $id, ['name' => trim($name)]);
        return $id;
    }

    /** @param array<int, int> $qtyByItem */
    public function create(int $supplierId, array $qtyByItem, ?string $expectedAt, string $actor, int $warehouseId = 1): int
    {
        $qtyByItem = array_filter($qtyByItem, fn ($q) => $q > 0);
        if ($qtyByItem === []) {
            throw new LedgerException('Add at least one product and quantity.');
        }
        return $this->db->transaction(function () use ($supplierId, $qtyByItem, $expectedAt, $actor, $warehouseId) {
            $now = $this->clock->now();
            $id = $this->db->insert(
                "INSERT INTO purchase_orders (po_number, supplier_id, warehouse_id, status, expected_at, created_by, created_at, updated_at) VALUES (?, ?, ?, 'draft', ?, ?, ?, ?)",
                ['TMP-' . bin2hex(random_bytes(6)), $supplierId, $warehouseId, $expectedAt ?: null, $actor, $now, $now],
            );
            $this->db->run('UPDATE purchase_orders SET po_number = ? WHERE id = ?', [sprintf('PO-%05d', $id), $id]);
            foreach ($qtyByItem as $item => $q) {
                $this->db->run('INSERT INTO purchase_order_lines (purchase_order_id, inventory_item_id, qty_ordered) VALUES (?, ?, ?)', [$id, $item, $q]);
            }
            $this->audit->log($actor, 'po.create', 'purchase_order', $id);
            return $id;
        });
    }

    public function markOrdered(int $poId, string $actor): void
    {
        $this->db->run("UPDATE purchase_orders SET status = 'ordered', updated_at = ? WHERE id = ? AND status = 'draft'", [$this->clock->now(), $poId]);
        $this->audit->log($actor, 'po.ordered', 'purchase_order', $poId);
    }

    /** Records one delivery against a line: good units, damaged units (counted, held back) and units the supplier will not send. */
    public function receive(int $lineId, int $good, int $damaged, int $missing, string $actor): void
    {
        if ($good < 0 || $damaged < 0 || $missing < 0 || $good + $damaged + $missing === 0) {
            throw new LedgerException('Enter what arrived.');
        }
        $this->db->transaction(function () use ($lineId, $good, $damaged, $missing, $actor) {
            $l = $this->db->one('SELECT l.*, po.status, po.po_number FROM purchase_order_lines l JOIN purchase_orders po ON po.id = l.purchase_order_id WHERE l.id = ? FOR UPDATE', [$lineId])
                ?? throw new LedgerException('Unknown purchase order line.');
            if (!in_array($l['status'], ['ordered', 'partially_received'], true)) {
                throw new LedgerException('Mark the purchase order as ordered before receiving it.');
            }
            $open = (int) $l['qty_ordered'] - (int) $l['qty_received'] - (int) $l['qty_missing'];
            if ($good + $damaged + $missing > $open) {
                throw new LedgerException("Only {$open} units are still expected on this line.");
            }
            if ($good + $damaged > 0) {
                $this->ledger->receivePurchase((int) $l['inventory_item_id'], $good, $damaged, $actor, $l['po_number']);
            }
            $this->db->run('UPDATE purchase_order_lines SET qty_received = qty_received + ?, qty_damaged = qty_damaged + ?, qty_missing = qty_missing + ? WHERE id = ?', [$good + $damaged, $damaged, $missing, $lineId]);
            $left = (int) $this->db->value('SELECT SUM(qty_ordered - qty_received - qty_missing) FROM purchase_order_lines WHERE purchase_order_id = ?', [$l['purchase_order_id']]);
            $this->db->run('UPDATE purchase_orders SET status = ?, updated_at = ? WHERE id = ?', [$left === 0 ? 'received' : 'partially_received', $this->clock->now(), $l['purchase_order_id']]);
        });
    }
}
