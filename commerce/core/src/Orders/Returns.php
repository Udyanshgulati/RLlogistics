<?php

declare(strict_types=1);

namespace Commerce\Core\Orders;

use Commerce\Core\Db\Database;
use Commerce\Core\Inventory\Ledger;
use Commerce\Core\Market\Currency;
use Commerce\Core\Support\Audit;
use Commerce\Core\Support\Clock;

/**
 * Returns and exchanges: open → receive → inspect (sellable / damaged) → refund or exchange.
 *
 * Receipt and inspection take running totals ("3 received so far"), and the ledger moves only the
 * difference, so pressing a button twice can never restore stock twice. Received units sit in
 * "awaiting inspection" and are not sellable until a person approves them.
 */
final class Returns
{
    public function __construct(
        private readonly Database $db,
        private readonly Ledger $ledger,
        private readonly Audit $audit,
        private readonly Clock $clock,
    ) {
    }

    /** @param array<int, int> $qtyByLine order line id => units being returned */
    public function open(int $orderId, array $qtyByLine, string $reason, string $kind, string $actor): int
    {
        if (trim($reason) === '') {
            throw new FulfilmentException('Add the return reason.');
        }
        if (!in_array($kind, ['return', 'exchange'], true)) {
            throw new \InvalidArgumentException('Unknown return type.');
        }
        return $this->db->transaction(function () use ($orderId, $qtyByLine, $reason, $kind, $actor) {
            $this->db->one('SELECT id FROM orders WHERE id = ? FOR UPDATE', [$orderId]) ?? throw new FulfilmentException('Unknown order.');
            $lines = [];
            foreach ($qtyByLine as $lineId => $q) {
                if ($q <= 0) {
                    continue;
                }
                $l = $this->db->one('SELECT * FROM order_lines WHERE id = ? AND order_id = ?', [$lineId, $orderId]) ?? throw new FulfilmentException('That item is not on this order.');
                $already = (int) $this->db->value("SELECT COALESCE(SUM(rl.qty), 0) FROM return_lines rl JOIN returns r ON r.id = rl.return_id WHERE rl.order_line_id = ? AND r.status <> 'cancelled'", [$lineId]);
                $returnable = (int) $l['qty_shipped'] - $already;
                if ($q > $returnable) {
                    throw new FulfilmentException("{$l['name']}: {$returnable} units can be returned ({$l['qty_shipped']} shipped, {$already} already in a return).");
                }
                if ($l['inventory_item_id'] === null) {
                    throw new FulfilmentException("{$l['name']} is not linked to stock. Link it before opening a return.");
                }
                $lines[] = [$l, $q];
            }
            if ($lines === []) {
                throw new FulfilmentException('Select at least one item to return.');
            }
            $now = $this->clock->now();
            $id = $this->db->insert(
                'INSERT INTO returns (order_id, rma_number, kind, reason, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$orderId, 'TMP-' . bin2hex(random_bytes(6)), $kind, trim($reason), $actor, $now, $now],
            );
            $this->db->run('UPDATE returns SET rma_number = ? WHERE id = ?', [sprintf('RMA-%06d', $id), $id]);
            foreach ($lines as [$l, $q]) {
                $this->db->run('INSERT INTO return_lines (return_id, order_line_id, inventory_item_id, qty) VALUES (?, ?, ?, ?)', [$id, $l['id'], $l['inventory_item_id'], $q]);
            }
            $this->event($orderId, 'return.opened', sprintf('%s RMA-%06d opened: %s', $kind === 'exchange' ? 'Exchange' : 'Return', $id, trim($reason)), $actor);
            $this->notify($orderId, $id, 'return.opened');
            return $id;
        });
    }

    /** Sets how many units of a line have physically arrived so far. */
    public function receive(int $returnLineId, int $receivedSoFar, string $actor): void
    {
        $this->db->transaction(function () use ($returnLineId, $receivedSoFar, $actor) {
            [$rl, $r] = $this->lockLine($returnLineId);
            if ($receivedSoFar < (int) $rl['qty_received'] || $receivedSoFar > (int) $rl['qty']) {
                throw new FulfilmentException("Enter a number from {$rl['qty_received']} to {$rl['qty']}.");
            }
            $diff = $receivedSoFar - (int) $rl['qty_received'];
            if ($diff === 0) {
                return;
            }
            $this->ledger->receiveReturn((int) $rl['inventory_item_id'], $diff, $actor, $r['rma_number']);
            $this->db->run('UPDATE return_lines SET qty_received = ? WHERE id = ?', [$receivedSoFar, $returnLineId]);
            $this->settle((int) $r['id']);
            $this->event((int) $r['order_id'], 'return.received', "{$r['rma_number']}: {$diff} unit(s) received, awaiting inspection", $actor);
        });
    }

    /** Sets the inspection result so far for a line: units approved for resale and units found damaged. */
    public function inspect(int $returnLineId, int $sellableSoFar, int $damagedSoFar, string $actor): void
    {
        $this->db->transaction(function () use ($returnLineId, $sellableSoFar, $damagedSoFar, $actor) {
            [$rl, $r] = $this->lockLine($returnLineId);
            if ($sellableSoFar < (int) $rl['qty_sellable'] || $damagedSoFar < (int) $rl['qty_damaged']) {
                throw new FulfilmentException('Inspection results cannot be reduced once recorded. Use a stock adjustment to correct them.');
            }
            if ($sellableSoFar + $damagedSoFar > (int) $rl['qty_received']) {
                throw new FulfilmentException("Only {$rl['qty_received']} unit(s) have been received.");
            }
            $sell = $sellableSoFar - (int) $rl['qty_sellable'];
            $dmg = $damagedSoFar - (int) $rl['qty_damaged'];
            if ($sell > 0) {
                $this->ledger->inspectReturn((int) $rl['inventory_item_id'], $sell, true, $actor, $r['rma_number']);
            }
            if ($dmg > 0) {
                $this->ledger->inspectReturn((int) $rl['inventory_item_id'], $dmg, false, $actor, $r['rma_number']);
            }
            $this->db->run('UPDATE return_lines SET qty_sellable = ?, qty_damaged = ? WHERE id = ?', [$sellableSoFar, $damagedSoFar, $returnLineId]);
            $this->settle((int) $r['id']);
            if ($sell + $dmg > 0) {
                $this->event((int) $r['order_id'], 'return.inspected', "{$r['rma_number']}: {$sell} approved for resale, {$dmg} damaged", $actor);
            }
        });
    }

    /**
     * Queues one refund request to the storefront, which runs it through the payment gateway.
     * refund_status stays "requested" until the storefront confirms; a second request is refused.
     */
    public function requestRefund(int $returnId, string $amount, string $actor): void
    {
        $this->db->transaction(function () use ($returnId, $amount, $actor) {
            $r = $this->db->one('SELECT r.*, o.currency, o.total_minor, o.refunded_minor FROM returns r JOIN orders o ON o.id = r.order_id WHERE r.id = ? FOR UPDATE', [$returnId]) ?? throw new FulfilmentException('Unknown return.');
            if (in_array($r['refund_status'], ['requested', 'succeeded'], true)) {
                throw new FulfilmentException('A refund has already been ' . ($r['refund_status'] === 'requested' ? 'requested' : 'made') . ' for this return.');
            }
            try {
                $minor = Currency::toMinor($amount, $r['currency']);
            } catch (\InvalidArgumentException) {
                throw new FulfilmentException("Enter the refund amount in {$r['currency']}.");
            }
            $max = (int) $r['total_minor'] - (int) $r['refunded_minor'];
            if ($minor <= 0 || $minor > $max) {
                throw new FulfilmentException('The refund must be more than zero and at most ' . Currency::fromMinor($max, $r['currency']) . " {$r['currency']}.");
            }
            $now = $this->clock->now();
            $this->db->run("UPDATE returns SET refund_status = 'requested', updated_at = ? WHERE id = ?", [$now, $returnId]);
            $this->db->run(
                "INSERT INTO outbox (topic, dedupe_key, payload, status, next_attempt_at, created_at, updated_at) VALUES ('store.refund', ?, ?, 'pending', ?, ?, ?)",
                ["store.refund:return:{$returnId}", json_encode(['return_id' => $returnId, 'order_id' => (int) $r['order_id'], 'amount_minor' => $minor, 'currency' => $r['currency']]), $now, $now, $now],
            );
            $this->event((int) $r['order_id'], 'refund.requested', "{$r['rma_number']}: refund of " . Currency::fromMinor($minor, $r['currency']) . " {$r['currency']} sent to the storefront", $actor);
            $this->audit->log($actor, 'refund.request', 'return', $returnId, ['amount_minor' => $minor, 'currency' => $r['currency']]);
        });
    }

    /** Called by the outbox worker with the storefront's answer to a refund request. */
    public function recordRefundResult(int $returnId, bool $succeeded, ?string $refundRef, ?string $error): void
    {
        $this->db->transaction(function () use ($returnId, $succeeded, $refundRef, $error) {
            $r = $this->db->one('SELECT * FROM returns WHERE id = ? FOR UPDATE', [$returnId]) ?? throw new FulfilmentException('Unknown return.');
            if ($r['refund_status'] !== 'requested') {
                return;
            }
            $this->db->run('UPDATE returns SET refund_status = ?, refund_ref = ?, updated_at = ? WHERE id = ?', [$succeeded ? 'succeeded' : 'failed', $refundRef, $this->clock->now(), $returnId]);
            $this->event((int) $r['order_id'], $succeeded ? 'refund.succeeded' : 'refund.failed', $succeeded ? "{$r['rma_number']}: refund confirmed by the payment provider" : "{$r['rma_number']}: refund failed: " . ($error ?? 'no reason given'), 'hub');
            if ($succeeded) {
                $this->notify((int) $r['order_id'], $returnId, 'refund.completed');
            }
            $this->settle($returnId);
        });
    }

    private function settle(int $returnId): void
    {
        $t = $this->db->one('SELECT SUM(qty) q, SUM(qty_received) rcv, SUM(qty_sellable + qty_damaged) insp FROM return_lines WHERE return_id = ?', [$returnId]);
        $r = $this->db->one('SELECT refund_status FROM returns WHERE id = ?', [$returnId]);
        $status = match (true) {
            (int) $t['rcv'] === 0 => 'requested',
            (int) $t['insp'] < (int) $t['q'] => 'received',
            in_array($r['refund_status'], ['succeeded', 'not_required'], true) => 'closed',
            default => 'inspected',
        };
        $this->db->run('UPDATE returns SET status = ?, updated_at = ? WHERE id = ?', [$status, $this->clock->now(), $returnId]);
    }

    private function lockLine(int $returnLineId): array
    {
        $rl = $this->db->one('SELECT * FROM return_lines WHERE id = ? FOR UPDATE', [$returnLineId]) ?? throw new FulfilmentException('Unknown return line.');
        $r = $this->db->one('SELECT * FROM returns WHERE id = ? FOR UPDATE', [$rl['return_id']]);
        if (in_array($r['status'], ['closed', 'cancelled'], true)) {
            throw new FulfilmentException('This return is closed.');
        }
        return [$rl, $r];
    }

    private function event(int $orderId, string $type, string $message, string $actor): void
    {
        $this->db->run('INSERT INTO order_events (order_id, type, message, actor, created_at) VALUES (?, ?, ?, ?, ?)', [$orderId, $type, mb_substr($message, 0, 500), $actor, $this->clock->now()]);
    }

    private function notify(int $orderId, int $returnId, string $event): void
    {
        $now = $this->clock->now();
        $this->db->run(
            "INSERT IGNORE INTO outbox (topic, dedupe_key, payload, status, next_attempt_at, created_at, updated_at) VALUES ('notify.customer', ?, ?, 'pending', ?, ?, ?)",
            ["notify:{$orderId}:{$event}:{$returnId}", json_encode(['order_id' => $orderId, 'return_id' => $returnId, 'event' => $event]), $now, $now, $now],
        );
    }
}
