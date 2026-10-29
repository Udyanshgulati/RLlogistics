<?php

declare(strict_types=1);

namespace Commerce\Core\Inventory;

use Commerce\Core\Db\Database;
use Commerce\Core\Support\Clock;

/**
 * The authoritative stock ledger. Nothing else writes stock_levels or stock_movements.
 *
 * Every change is one conditional UPDATE whose WHERE clause re-checks the availability rule against the
 * row's latest committed values (InnoDB current read under a row lock), followed by an append-only movement
 * row in the same transaction. Two checkouts racing for the last unit therefore serialise on the row: the
 * second UPDATE matches zero rows and the reservation is refused. The CHECK constraints on stock_levels
 * are a second line of defence.
 *
 * Reservation states:
 *   held ──confirm──▶ confirmed ──fulfil──▶ partially_fulfilled ──fulfil──▶ fulfilled
 *    │                   │                          │
 *    ├─release──▶ released ◀──────release──────────┘ (remaining units only)
 *    └─expire───▶ expired ──confirm (late payment, stock permitting)──▶ confirmed
 */
final class Ledger
{
    public const ACTIVE_STATES = ['held', 'confirmed', 'partially_fulfilled'];

    public function __construct(
        private readonly Database $db,
        private readonly Clock $clock,
        private readonly int $warehouseId = 1,
    ) {
    }

    // ── Reads ───────────────────────────────────────────────────────────────

    public function level(int $itemId, ?int $warehouseId = null): StockLevel
    {
        $row = $this->db->one('SELECT * FROM stock_levels WHERE inventory_item_id = ? AND warehouse_id = ?', [$itemId, $warehouseId ?? $this->warehouseId]);
        if ($row === null) {
            throw new LedgerException("No stock record exists for item {$itemId} in this warehouse.");
        }
        return StockLevel::fromRow($row);
    }

    public function reservation(int $id): ?array
    {
        $r = $this->db->one('SELECT * FROM reservations WHERE id = ?', [$id]);
        if ($r !== null) {
            $r['lines'] = $this->db->all('SELECT * FROM reservation_lines WHERE reservation_id = ? ORDER BY inventory_item_id', [$id]);
        }
        return $r;
    }

    public function reservationByKey(string $key): ?array
    {
        $id = $this->db->value('SELECT id FROM reservations WHERE idempotency_key = ?', [$key]);
        return $id === null ? null : $this->reservation((int) $id);
    }

    // ── Physical stock ──────────────────────────────────────────────────────

    /** Creates the zero balance row for a new item. Opening stock is then recorded as a movement. */
    public function open(int $itemId, int $openingQty, string $actor): StockLevel
    {
        return $this->db->transaction(function () use ($itemId, $openingQty, $actor) {
            $this->db->run(
                'INSERT INTO stock_levels (inventory_item_id, warehouse_id, updated_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE inventory_item_id = inventory_item_id',
                [$itemId, $this->warehouseId, $this->clock->now()],
            );
            if ($openingQty < 0) {
                throw new LedgerException('Opening stock cannot be negative.');
            }
            return $openingQty > 0
                ? $this->apply($itemId, new Delta(onHand: $openingQty), 'opening', $actor, 'Opening stock')
                : $this->level($itemId);
        });
    }

    /** What the adjustment form shows before "Confirm adjustment". Writes nothing. */
    public function preview(int $itemId, Adjustment $type, int $qty): array
    {
        $before = $this->level($itemId);
        $problem = $this->validateAdjustment($type, $qty, null, false);
        $after = $problem === null ? $before->with($type->delta($qty)) : $before;
        if ($problem === null && !$after->isValid()) {
            $problem = self::explainInvalid($before, $type->delta($qty));
        }
        return ['before' => $before, 'after' => $after, 'allowed' => $problem === null, 'problem' => $problem];
    }

    public function adjust(int $itemId, Adjustment $type, int $qty, string $actor, ?string $reason = null): StockLevel
    {
        $problem = $this->validateAdjustment($type, $qty, $reason, true);
        if ($problem !== null) {
            throw new LedgerException($problem);
        }
        return $this->db->transaction(fn () => $this->apply($itemId, $type->delta($qty), 'adjust.' . $type->value, $actor, $reason));
    }

    /** Physical count: sets on hand to the counted number and records the difference. */
    public function stocktake(int $itemId, int $counted, string $actor, string $reason = 'Stocktake'): StockLevel
    {
        if ($counted < 0) {
            throw new LedgerException('A counted quantity cannot be negative.');
        }
        return $this->db->transaction(function () use ($itemId, $counted, $actor, $reason) {
            $current = $this->lockLevel($itemId);
            $diff = $counted - $current->onHand;
            if ($diff === 0) {
                $this->movement($current, new Delta(), 'stocktake', $actor, $reason . ' (no difference)');
                return $current;
            }
            return $this->apply($itemId, new Delta(onHand: $diff), 'stocktake', $actor, $reason);
        });
    }

    /** Removes damaged units from the building (thrown away, sent back). */
    public function writeOffDamaged(int $itemId, int $qty, string $actor, string $reason): StockLevel
    {
        self::positive($qty);
        return $this->db->transaction(fn () => $this->apply($itemId, new Delta(onHand: -$qty, damaged: -$qty), 'damaged.write_off', $actor, $reason));
    }

    /** Goods received against a purchase order. Damaged units are counted on hand but held back from sale. */
    public function receivePurchase(int $itemId, int $good, int $damaged, string $actor, string $poRef): StockLevel
    {
        if ($good < 0 || $damaged < 0 || $good + $damaged === 0) {
            throw new LedgerException('Enter the number of units received.');
        }
        return $this->db->transaction(fn () => $this->apply($itemId, new Delta(onHand: $good + $damaged, damaged: $damaged), 'purchase.receive', $actor, null, 'purchase_order', $poRef));
    }

    /** A customer return arrived. It is on hand but not sellable until inspected. */
    public function receiveReturn(int $itemId, int $qty, string $actor, string $returnRef): StockLevel
    {
        self::positive($qty);
        return $this->db->transaction(fn () => $this->apply($itemId, new Delta(onHand: $qty, quarantine: $qty), 'return.receive', $actor, null, 'return', $returnRef));
    }

    /** Inspection result for returned units: back to sale, or into the damaged hold. */
    public function inspectReturn(int $itemId, int $qty, bool $sellable, string $actor, string $returnRef): StockLevel
    {
        self::positive($qty);
        $delta = $sellable ? new Delta(quarantine: -$qty) : new Delta(damaged: $qty, quarantine: -$qty);
        return $this->db->transaction(fn () => $this->apply($itemId, $delta, $sellable ? 'return.restock' : 'return.damaged', $actor, null, 'return', $returnRef));
    }

    /**
     * Undo for a recent manual adjustment: records the opposite movement (history is never deleted).
     * Allowed once, for the person who made it, within $windowSeconds, and only if stock still allows it.
     */
    public function reverseAdjustment(int $movementId, string $actor, int $windowSeconds = 600): StockLevel
    {
        return $this->db->transaction(function () use ($movementId, $actor, $windowSeconds) {
            $m = $this->db->one('SELECT * FROM stock_movements WHERE id = ?', [$movementId]) ?? throw new LedgerException('That stock change no longer exists.');
            if (!str_starts_with($m['type'], 'adjust.') || $m['type'] === 'adjust.undo') {
                throw new LedgerException('Only manual adjustments can be undone.');
            }
            if ($m['actor'] !== $actor) {
                throw new LedgerException('Only the person who made an adjustment can undo it.');
            }
            if (new \DateTimeImmutable($m['created_at'], new \DateTimeZone('UTC')) < $this->clock->now()->modify("-{$windowSeconds} seconds")) {
                throw new LedgerException('This adjustment is too old to undo. Record a stock correction instead.');
            }
            $this->lockLevel((int) $m['inventory_item_id'], (int) $m['warehouse_id']);
            if ($this->db->value("SELECT 1 FROM stock_movements WHERE ref_type = 'movement' AND ref_id = ? LIMIT 1", [(string) $movementId])) {
                throw new LedgerException('This adjustment has already been undone.');
            }
            $inverse = new Delta(-(int) $m['d_on_hand'], -(int) $m['d_reserved'], -(int) $m['d_damaged'], -(int) $m['d_quarantine']);
            return $this->apply((int) $m['inventory_item_id'], $inverse, 'adjust.undo', $actor, "Undo of change #{$movementId}", 'movement', (string) $movementId, (int) $m['warehouse_id']);
        });
    }

    // ── Reservations ────────────────────────────────────────────────────────

    /**
     * Holds stock for a storefront order, all lines or none.
     *
     * @param array<int, int> $qtyByItem inventory item id => units
     * @param int $ttlSeconds   when an unpaid hold becomes due for the expiry check
     * @param int $hardTtlSeconds the latest an unpaid hold can be extended to
     */
    public function reserve(int $storeId, string $orderRef, array $qtyByItem, string $key, int $ttlSeconds, int $hardTtlSeconds): ReservationResult
    {
        $lines = self::normaliseLines($qtyByItem);
        if ($ttlSeconds <= 0 || $hardTtlSeconds < $ttlSeconds) {
            throw new \InvalidArgumentException('Invalid reservation lifetime.');
        }
        try {
            return $this->db->transaction(fn () => $this->reserveTx($storeId, $orderRef, $lines, $key, $ttlSeconds, $hardTtlSeconds));
        } catch (\PDOException $e) {
            // A concurrent call with the same key committed first: replay it.
            if (Database::isDuplicateKey($e)) {
                return $this->db->transaction(fn () => $this->reserveTx($storeId, $orderRef, $lines, $key, $ttlSeconds, $hardTtlSeconds));
            }
            throw $e;
        }
    }

    /** Payment succeeded. Safe to call any number of times. */
    public function confirm(int $reservationId, ?string $paymentRef, string $actor): ReservationResult
    {
        return $this->db->transaction(function () use ($reservationId, $paymentRef, $actor) {
            $r = $this->lockReservation($reservationId);
            $now = $this->clock->now();
            switch ($r['state']) {
                case 'held':
                    $this->db->run("UPDATE reservations SET state = 'confirmed', expires_at = NULL, hard_expires_at = NULL, payment_ref = ?, exception = NULL, updated_at = ? WHERE id = ?", [$paymentRef, $now, $reservationId]);
                    return new ReservationResult($reservationId, 'confirmed');
                case 'confirmed':
                case 'partially_fulfilled':
                case 'fulfilled':
                    return new ReservationResult($reservationId, $r['state'], replayed: true);
                case 'expired':
                    // Payment arrived after the hold lapsed. Take the stock again if it is still there.
                    try {
                        $this->reacquire($r, 'reserve.late_payment', $actor);
                    } catch (InsufficientStock) {
                        $this->db->run("UPDATE reservations SET exception = 'paid_after_expiry_no_stock', payment_ref = ?, updated_at = ? WHERE id = ?", [$paymentRef, $now, $reservationId]);
                        return new ReservationResult($reservationId, 'expired', ok: false, exception: 'paid_after_expiry_no_stock');
                    }
                    $this->db->run("UPDATE reservations SET state = 'confirmed', expires_at = NULL, hard_expires_at = NULL, payment_ref = ?, exception = NULL, updated_at = ? WHERE id = ?", [$paymentRef, $now, $reservationId]);
                    return new ReservationResult($reservationId, 'confirmed');
                default: // released: the order was cancelled, then paid. A person decides (refund or reinstate).
                    $this->db->run("UPDATE reservations SET exception = 'paid_after_release', payment_ref = ?, updated_at = ? WHERE id = ?", [$paymentRef, $now, $reservationId]);
                    return new ReservationResult($reservationId, 'released', ok: false, exception: 'paid_after_release');
            }
        });
    }

    /** Pushes an unpaid hold's expiry check later (e.g. the gateway reports the payment as pending), never past the hard limit. */
    public function extend(int $reservationId, int $seconds, string $actor): ReservationResult
    {
        return $this->db->transaction(function () use ($reservationId, $seconds) {
            $r = $this->lockReservation($reservationId);
            if ($r['state'] !== 'held') {
                return new ReservationResult($reservationId, $r['state'], replayed: true);
            }
            $until = $this->clock->now()->modify("+{$seconds} seconds");
            $hard = new \DateTimeImmutable($r['hard_expires_at'], new \DateTimeZone('UTC'));
            if ($until > $hard) {
                $until = $hard;
            }
            $this->db->run('UPDATE reservations SET expires_at = ?, updated_at = ? WHERE id = ?', [$until, $this->clock->now(), $reservationId]);
            return new ReservationResult($reservationId, 'held');
        });
    }

    /**
     * Gives back units that will not be shipped (payment failed, order cancelled, line removed).
     * Releasing twice changes nothing. Units already dispatched are never released; they come back as returns.
     *
     * @param array<int, int>|null $qtyByItem release only these units; null = everything not yet dispatched
     */
    public function release(int $reservationId, string $reason, string $actor, ?array $qtyByItem = null): ReservationResult
    {
        return $this->db->transaction(function () use ($reservationId, $reason, $actor, $qtyByItem) {
            $r = $this->lockReservation($reservationId);
            if (!in_array($r['state'], self::ACTIVE_STATES, true)) {
                return new ReservationResult($reservationId, $r['state'], replayed: true, ok: $r['state'] !== 'fulfilled');
            }
            $released = 0;
            foreach ($this->lines($reservationId) as $line) {
                $remaining = (int) $line['qty'] - (int) $line['fulfilled_qty'] - (int) $line['released_qty'];
                $q = $qtyByItem === null ? $remaining : min($remaining, (int) ($qtyByItem[(int) $line['inventory_item_id']] ?? 0));
                if ($q <= 0) {
                    continue;
                }
                $this->apply((int) $line['inventory_item_id'], new Delta(reserved: -$q), 'release', $actor, $reason, 'reservation', (string) $reservationId, (int) $line['warehouse_id']);
                $this->db->run('UPDATE reservation_lines SET released_qty = released_qty + ? WHERE id = ?', [$q, $line['id']]);
                $released += $q;
            }
            $state = $this->settledState($reservationId);
            $this->db->run('UPDATE reservations SET state = ?, updated_at = ? WHERE id = ?', [$state, $this->clock->now(), $reservationId]);
            return new ReservationResult($reservationId, $state, replayed: $released === 0);
        });
    }

    /** Unpaid holds whose expiry check is due, oldest first. */
    public function dueForExpiry(int $limit = 100): array
    {
        return array_map('intval', array_column($this->db->all(
            "SELECT id FROM reservations WHERE state = 'held' AND expires_at <= ? ORDER BY expires_at LIMIT " . max(1, $limit),
            [$this->clock->now()],
        ), 'id'));
    }

    /** Lets an unpaid hold lapse. Returns false (and changes nothing) unless it is still held and due. */
    public function expire(int $reservationId, string $actor): bool
    {
        return $this->db->transaction(function () use ($reservationId, $actor) {
            $r = $this->lockReservation($reservationId);
            if ($r['state'] !== 'held' || new \DateTimeImmutable($r['expires_at'], new \DateTimeZone('UTC')) > $this->clock->now()) {
                return false;
            }
            foreach ($this->lines($reservationId) as $line) {
                $remaining = (int) $line['qty'] - (int) $line['fulfilled_qty'] - (int) $line['released_qty'];
                if ($remaining > 0) {
                    $this->apply((int) $line['inventory_item_id'], new Delta(reserved: -$remaining), 'expire', $actor, 'Unpaid hold expired', 'reservation', (string) $reservationId, (int) $line['warehouse_id']);
                    $this->db->run('UPDATE reservation_lines SET released_qty = released_qty + ? WHERE id = ?', [$remaining, $line['id']]);
                }
            }
            $this->db->run("UPDATE reservations SET state = 'expired', updated_at = ? WHERE id = ?", [$this->clock->now(), $reservationId]);
            return true;
        });
    }

    /**
     * Units physically left the warehouse in a shipment. Reserved units become shipped units:
     * on hand and reserved both go down. Calling again with the same shipment reference changes nothing.
     *
     * @param array<int, int> $qtyByItem
     */
    public function fulfil(int $reservationId, array $qtyByItem, string $shipmentRef, string $actor): ReservationResult
    {
        $wanted = self::normaliseLines($qtyByItem);
        return $this->db->transaction(function () use ($reservationId, $wanted, $shipmentRef, $actor) {
            $r = $this->lockReservation($reservationId);
            $seen = $this->db->value("SELECT 1 FROM stock_movements WHERE ref_type = 'shipment' AND ref_id = ? LIMIT 1", [$shipmentRef]);
            if ($seen) {
                return new ReservationResult($reservationId, $r['state'], replayed: true);
            }
            if (!in_array($r['state'], ['confirmed', 'partially_fulfilled'], true)) {
                throw new LedgerException(match ($r['state']) {
                    'held' => 'This order is not paid yet, so it cannot be dispatched.',
                    'fulfilled' => 'Everything on this order has already been dispatched.',
                    default => 'This order\'s stock was released, so it cannot be dispatched.',
                });
            }
            $byItem = [];
            foreach ($this->lines($reservationId) as $line) {
                $byItem[(int) $line['inventory_item_id']] = $line;
            }
            foreach ($wanted as $itemId => $q) {
                $line = $byItem[$itemId] ?? null;
                $remaining = $line === null ? 0 : (int) $line['qty'] - (int) $line['fulfilled_qty'] - (int) $line['released_qty'];
                if ($q > $remaining) {
                    throw new LedgerException("Item {$itemId}: {$q} units to dispatch but only {$remaining} are reserved for this order.");
                }
            }
            foreach ($wanted as $itemId => $q) {
                $line = $byItem[$itemId];
                $this->apply($itemId, new Delta(onHand: -$q, reserved: -$q), 'dispatch', $actor, null, 'shipment', $shipmentRef, (int) $line['warehouse_id']);
                $this->db->run('UPDATE reservation_lines SET fulfilled_qty = fulfilled_qty + ? WHERE id = ?', [$q, $line['id']]);
            }
            $state = $this->settledState($reservationId);
            $this->db->run('UPDATE reservations SET state = ?, updated_at = ? WHERE id = ?', [$state, $this->clock->now(), $reservationId]);
            return new ReservationResult($reservationId, $state);
        });
    }

    // ── Internals ───────────────────────────────────────────────────────────

    private function reserveTx(int $storeId, string $orderRef, array $lines, string $key, int $ttl, int $hardTtl): ReservationResult
    {
        $now = $this->clock->now();
        $existing = $this->db->one('SELECT * FROM reservations WHERE idempotency_key = ? FOR UPDATE', [$key]);
        if ($existing !== null) {
            $had = [];
            foreach ($this->lines((int) $existing['id']) as $l) {
                $had[(int) $l['inventory_item_id']] = (int) $l['qty'];
            }
            ksort($had);
            if ((int) $existing['store_id'] !== $storeId || $existing['order_ref'] !== $orderRef || $had !== $lines) {
                throw new IdempotencyConflict('This reservation key was already used for different items.');
            }
            if ($existing['state'] === 'expired') {
                // Customer retried payment after the hold lapsed: hold again if stock allows.
                $this->reacquire($existing, 'reserve.retry', 'store:' . $storeId);
                $this->db->run("UPDATE reservations SET state = 'held', expires_at = ?, hard_expires_at = ?, updated_at = ? WHERE id = ?", [$now->modify("+{$ttl} seconds"), $now->modify("+{$hardTtl} seconds"), $now, $existing['id']]);
                return new ReservationResult((int) $existing['id'], 'held');
            }
            return new ReservationResult((int) $existing['id'], $existing['state'], ok: $existing['state'] !== 'released', replayed: true);
        }

        $id = $this->db->insert(
            "INSERT INTO reservations (store_id, order_ref, idempotency_key, state, expires_at, hard_expires_at, created_at, updated_at) VALUES (?, ?, ?, 'held', ?, ?, ?, ?)",
            [$storeId, $orderRef, $key, $now->modify("+{$ttl} seconds"), $now->modify("+{$hardTtl} seconds"), $now, $now],
        );
        foreach ($lines as $itemId => $q) {
            $this->db->run('INSERT INTO reservation_lines (reservation_id, inventory_item_id, warehouse_id, qty) VALUES (?, ?, ?, ?)', [$id, $itemId, $this->warehouseId, $q]);
        }
        $this->takeLines($lines, 'reserve', 'store:' . $storeId, (string) $id);
        return new ReservationResult($id, 'held');
    }

    private function reacquire(array $reservation, string $type, string $actor): void
    {
        $lines = [];
        foreach ($this->lines((int) $reservation['id']) as $l) {
            $q = (int) $l['qty'] - (int) $l['fulfilled_qty'];
            if ($q > 0) {
                $lines[(int) $l['inventory_item_id']] = $q;
            }
        }
        $this->takeLines($lines, $type, $actor, (string) $reservation['id']);
        $this->db->run('UPDATE reservation_lines SET released_qty = 0 WHERE reservation_id = ?', [$reservation['id']]);
    }

    /** Reserves every line or throws InsufficientStock listing every short line. Lines are taken in item-id order to avoid deadlocks. */
    private function takeLines(array $lines, string $type, string $actor, string $reservationRef): void
    {
        try {
            foreach ($lines as $itemId => $q) {
                $this->apply($itemId, new Delta(reserved: $q), $type, $actor, null, 'reservation', $reservationRef);
            }
        } catch (InsufficientStock) {
            $short = [];
            foreach ($lines as $itemId => $q) {
                $avail = $this->level($itemId)->available();
                if ($avail < $q) {
                    $short[$itemId] = ['requested' => $q, 'available' => max(0, $avail)];
                }
            }
            throw new InsufficientStock($short ?: [array_key_first($lines) => ['requested' => reset($lines), 'available' => 0]]);
        }
    }

    /** The one place stock balances change. */
    private function apply(int $itemId, Delta $d, string $type, string $actor, ?string $reason, ?string $refType = null, ?string $refId = null, ?int $warehouseId = null): StockLevel
    {
        $wh = $warehouseId ?? $this->warehouseId;
        $changed = $this->db->run(
            'UPDATE stock_levels SET on_hand = on_hand + ?, reserved = reserved + ?, damaged = damaged + ?, quarantine = quarantine + ?, updated_at = ?
             WHERE inventory_item_id = ? AND warehouse_id = ?
               AND on_hand + ? >= 0 AND reserved + ? >= 0 AND damaged + ? >= 0 AND quarantine + ? >= 0
               AND (reserved + ?) + (damaged + ?) + (quarantine + ?) <= on_hand + ?',
            [$d->onHand, $d->reserved, $d->damaged, $d->quarantine, $this->clock->now(), $itemId, $wh,
             $d->onHand, $d->reserved, $d->damaged, $d->quarantine,
             $d->reserved, $d->damaged, $d->quarantine, $d->onHand],
        )->rowCount();
        if ($changed !== 1) {
            $current = $this->level($itemId, $wh); // throws if the row does not exist
            if ($d->availableChange() < 0 && $current->available() + $d->availableChange() < 0) {
                throw new InsufficientStock([$itemId => ['requested' => -$d->availableChange(), 'available' => max(0, $current->available())]]);
            }
            throw new LedgerException(self::explainInvalid($current, $d));
        }
        $after = $this->lockLevel($itemId, $wh);
        $this->movement($after, $d, $type, $actor, $reason, $refType, $refId);
        if ($d->availableChange() !== 0) {
            $this->afterAvailabilityChange($itemId);
        }
        return $after;
    }

    private function movement(StockLevel $after, Delta $d, string $type, string $actor, ?string $reason, ?string $refType = null, ?string $refId = null): void
    {
        $this->db->run(
            'INSERT INTO stock_movements (inventory_item_id, warehouse_id, type, d_on_hand, d_reserved, d_damaged, d_quarantine, on_hand_after, reserved_after, damaged_after, quarantine_after, reason, ref_type, ref_id, actor, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$after->itemId, $after->warehouseId, $type, $d->onHand, $d->reserved, $d->damaged, $d->quarantine,
             $after->onHand, $after->reserved, $after->damaged, $after->quarantine,
             $reason === null ? null : mb_substr($reason, 0, 500), $refType, $refId, $actor, $this->clock->now()],
        );
    }

    /** Queues a stock push to every storefront selling the item, and opens or resolves its low-stock alert. */
    private function afterAvailabilityChange(int $itemId): void
    {
        $now = $this->clock->now();
        foreach ($this->db->all("SELECT id FROM listings WHERE inventory_item_id = ? AND status = 'mapped'", [$itemId]) as $listing) {
            $this->db->run(
                "INSERT INTO outbox (topic, dedupe_key, payload, status, next_attempt_at, created_at, updated_at) VALUES ('stock.push', ?, ?, 'pending', ?, ?, ?)
                 ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)",
                ['stock.push:listing:' . $listing['id'], json_encode(['listing_id' => (int) $listing['id']]), $now, $now, $now],
            );
        }

        $row = $this->db->one(
            'SELECT i.reorder_level, COALESCE(SUM(l.on_hand - l.reserved - l.damaged - l.quarantine), 0) AS available
             FROM inventory_items i LEFT JOIN stock_levels l ON l.inventory_item_id = i.id WHERE i.id = ? GROUP BY i.id',
            [$itemId],
        );
        $available = (int) $row['available'];
        $kind = $available <= 0 ? 'out' : ($available < (int) $row['reorder_level'] ? 'low' : null);
        $open = $this->db->one('SELECT id, kind FROM low_stock_alerts WHERE inventory_item_id = ? AND resolved_at IS NULL FOR UPDATE', [$itemId]);
        if ($open !== null && $open['kind'] === $kind) {
            return; // same unresolved condition: no new alert
        }
        if ($open !== null) {
            $this->db->run('UPDATE low_stock_alerts SET resolved_at = ? WHERE id = ?', [$now, $open['id']]);
        }
        if ($kind !== null) {
            $alertId = $this->db->insert('INSERT INTO low_stock_alerts (inventory_item_id, kind, available_at_open, opened_at) VALUES (?, ?, ?, ?)', [$itemId, $kind, $available, $now]);
            $this->db->run(
                "INSERT INTO outbox (topic, dedupe_key, payload, status, next_attempt_at, created_at, updated_at) VALUES ('alert.stock', ?, ?, 'pending', ?, ?, ?)",
                ['alert.stock:' . $alertId, json_encode(['alert_id' => $alertId, 'inventory_item_id' => $itemId, 'kind' => $kind]), $now, $now, $now],
            );
        }
    }

    private function lockLevel(int $itemId, ?int $wh = null): StockLevel
    {
        $row = $this->db->one('SELECT * FROM stock_levels WHERE inventory_item_id = ? AND warehouse_id = ? FOR UPDATE', [$itemId, $wh ?? $this->warehouseId]);
        if ($row === null) {
            throw new LedgerException("No stock record exists for item {$itemId} in this warehouse.");
        }
        return StockLevel::fromRow($row);
    }

    private function lockReservation(int $id): array
    {
        $r = $this->db->one('SELECT * FROM reservations WHERE id = ? FOR UPDATE', [$id]);
        if ($r === null) {
            throw new LedgerException("Reservation {$id} does not exist.");
        }
        return $r;
    }

    private function lines(int $reservationId): array
    {
        return $this->db->all('SELECT * FROM reservation_lines WHERE reservation_id = ? ORDER BY inventory_item_id', [$reservationId]);
    }

    /** State after a release or fulfilment: done when nothing is left to ship, partially fulfilled once anything shipped. */
    private function settledState(int $reservationId): string
    {
        $t = $this->db->one('SELECT SUM(qty) q, SUM(fulfilled_qty) f, SUM(released_qty) r FROM reservation_lines WHERE reservation_id = ?', [$reservationId]);
        $shipped = (int) $t['f'] > 0;
        if ((int) $t['q'] - (int) $t['f'] - (int) $t['r'] === 0) {
            return $shipped ? 'fulfilled' : 'released';
        }
        return $shipped ? 'partially_fulfilled' : (string) $this->db->value('SELECT state FROM reservations WHERE id = ?', [$reservationId]);
    }

    private function validateAdjustment(Adjustment $type, int $qty, ?string $reason, bool $checkReason): ?string
    {
        if ($qty === 0 || ($qty < 0 && !$type->allowsNegative())) {
            return $type->allowsNegative() ? 'Enter a quantity other than zero.' : 'Enter a quantity greater than zero.';
        }
        if ($checkReason && $type->requiresReason() && trim((string) $reason) === '') {
            return 'Add a reason for this adjustment.';
        }
        return null;
    }

    private static function explainInvalid(StockLevel $before, Delta $d): string
    {
        $after = $before->with($d);
        if ($after->onHand < 0) {
            return "Only {$before->onHand} units are on hand.";
        }
        if ($after->available() < 0) {
            $holds = $before->reserved + $before->damaged + $before->quarantine;
            return "This would leave fewer units on hand ({$after->onHand}) than are reserved or held ({$holds}). Release or ship those first.";
        }
        if ($after->damaged < 0) {
            return "Only {$before->damaged} units are in the damaged hold.";
        }
        if ($after->quarantine < 0) {
            return "Only {$before->quarantine} units are awaiting inspection.";
        }
        return 'This change would make the stock record inconsistent.';
    }

    /** @return array<int, int> merged, validated, sorted by item id */
    private static function normaliseLines(array $qtyByItem): array
    {
        if ($qtyByItem === []) {
            throw new \InvalidArgumentException('A reservation needs at least one line.');
        }
        $out = [];
        foreach ($qtyByItem as $item => $q) {
            if (!is_int($item) || !is_int($q) || $item <= 0 || $q <= 0) {
                throw new \InvalidArgumentException('Lines must be positive integer item ids and quantities.');
            }
            $out[$item] = $q;
        }
        ksort($out);
        return $out;
    }

    private static function positive(int $qty): void
    {
        if ($qty <= 0) {
            throw new LedgerException('Enter a quantity greater than zero.');
        }
    }
}
