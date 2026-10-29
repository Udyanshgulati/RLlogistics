<?php

declare(strict_types=1);

namespace Commerce\Core\Inventory;

use Commerce\Core\Db\Database;

/**
 * Checks that the ledger agrees with itself. Run nightly and on demand from Integration health.
 * It reports problems; it never "fixes" balances. A person decides, then records a stocktake or adjustment.
 */
final class Reconciler
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array{check: string, inventory_item_id: int, expected: int, actual: int}> */
    public function problems(): array
    {
        $out = [];
        // 1. Balances equal the sum of all movements.
        $rows = $this->db->all(
            'SELECT l.inventory_item_id, l.on_hand, l.reserved, l.damaged, l.quarantine,
                    COALESCE(SUM(m.d_on_hand), 0) mo, COALESCE(SUM(m.d_reserved), 0) mr, COALESCE(SUM(m.d_damaged), 0) md, COALESCE(SUM(m.d_quarantine), 0) mq
             FROM stock_levels l LEFT JOIN stock_movements m ON m.inventory_item_id = l.inventory_item_id AND m.warehouse_id = l.warehouse_id
             GROUP BY l.inventory_item_id, l.warehouse_id',
        );
        foreach ($rows as $r) {
            foreach (['on_hand' => 'mo', 'reserved' => 'mr', 'damaged' => 'md', 'quarantine' => 'mq'] as $col => $sum) {
                if ((int) $r[$col] !== (int) $r[$sum]) {
                    $out[] = ['check' => "{$col}_matches_movements", 'inventory_item_id' => (int) $r['inventory_item_id'], 'expected' => (int) $r[$sum], 'actual' => (int) $r[$col]];
                }
            }
        }
        // 2. Reserved equals the open quantity on active reservations.
        $rows = $this->db->all(
            "SELECT l.inventory_item_id, l.reserved, COALESCE(SUM(CASE WHEN r.state IN ('held','confirmed','partially_fulfilled') THEN rl.qty - rl.fulfilled_qty - rl.released_qty ELSE 0 END), 0) open_qty
             FROM stock_levels l
             LEFT JOIN reservation_lines rl ON rl.inventory_item_id = l.inventory_item_id AND rl.warehouse_id = l.warehouse_id
             LEFT JOIN reservations r ON r.id = rl.reservation_id
             GROUP BY l.inventory_item_id, l.warehouse_id",
        );
        foreach ($rows as $r) {
            if ((int) $r['reserved'] !== (int) $r['open_qty']) {
                $out[] = ['check' => 'reserved_matches_reservations', 'inventory_item_id' => (int) $r['inventory_item_id'], 'expected' => (int) $r['open_qty'], 'actual' => (int) $r['reserved']];
            }
        }
        return $out;
    }
}
