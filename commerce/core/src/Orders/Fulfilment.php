<?php

declare(strict_types=1);

namespace Commerce\Core\Orders;

use Commerce\Core\Db\Database;
use Commerce\Core\Inventory\Ledger;
use Commerce\Core\Support\Audit;
use Commerce\Core\Support\Clock;

/**
 * Warehouse workflow: start processing → picked → packed → shipment → dispatched → delivered.
 *
 * The dashboard asks nextActions() what to offer, and every action re-checks the state on the server.
 * Payment status, cancellations and refunds are never changed here: those requests are queued to the
 * storefront (which runs them through the payment gateway) and the result comes back as an order snapshot.
 */
final class Fulfilment
{
    public function __construct(
        private readonly Database $db,
        private readonly Ledger $ledger,
        private readonly Audit $audit,
        private readonly Clock $clock,
    ) {
    }

    /** @return list<string> the actions the current state allows, most important first */
    public function nextActions(int $orderId): array
    {
        $o = $this->order($orderId);
        $actions = [];
        $open = !in_array($o['fulfilment_status'], ['shipped', 'delivered', 'cancelled'], true);
        if ($o['attention'] !== null) {
            $actions[] = 'resolve_attention';
        }
        if ($o['payment_status'] === 'paid' && $o['attention'] === null) {
            $actions = array_merge($actions, match ($o['fulfilment_status']) {
                'unfulfilled' => ['start_processing'],
                'processing' => ['mark_picked'],
                'picked' => ['mark_packed'],
                'packed', 'partially_shipped' => $this->draftShipment($orderId) ? ['mark_dispatched'] : ['create_shipment'],
                'shipped' => $this->hasUndeliveredShipment($orderId) ? ['mark_delivered'] : [],
                default => [],
            });
        }
        if ($o['payment_status'] === 'pending' && $open) {
            $actions[] = 'request_mark_paid';
        }
        // Cancelling stops being offered once a label exists; void the shipment with the carrier first.
        if (in_array($o['payment_status'], ['pending', 'paid'], true) && in_array($o['fulfilment_status'], ['unfulfilled', 'processing', 'picked', 'packed'], true) && !$this->draftShipment($orderId)) {
            $actions[] = 'request_cancel';
        }
        return $actions;
    }

    public function startProcessing(int $orderId, string $actor): void
    {
        $this->transition($orderId, ['unfulfilled'], 'processing', 'processing', 'Processing started', $actor, requirePaid: true);
    }

    public function markPicked(int $orderId, string $actor): void
    {
        $this->transition($orderId, ['processing'], 'picked', 'picked', 'Items picked', $actor, requirePaid: true);
        $this->db->run('UPDATE order_lines SET qty_picked = qty WHERE order_id = ?', [$orderId]);
    }

    public function markPacked(int $orderId, string $actor): void
    {
        $this->transition($orderId, ['picked'], 'packed', 'packed', 'Order packed', $actor, requirePaid: true);
    }

    /** Pick list rows for one or more orders, grouped for the warehouse floor. */
    public function pickList(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }
        $in = implode(',', array_map('intval', $orderIds));
        return $this->db->all(
            "SELECT o.order_number, b.name AS brand, ol.name, ol.sku, ol.option_label, ol.qty - ol.qty_shipped AS qty
             FROM order_lines ol JOIN orders o ON o.id = ol.order_id JOIN stores s ON s.id = o.store_id JOIN brands b ON b.id = s.brand_id
             WHERE o.id IN ({$in}) AND ol.qty > ol.qty_shipped ORDER BY ol.sku, o.order_number",
        );
    }

    /**
     * Records a shipment for some or all remaining units. With a carrier integration the label and tracking
     * number come from the carrier; without one, staff type the tracking number from the carrier's own portal.
     *
     * @param array<int, int>|null $qtyByLine order line id => units; null = everything not yet shipped
     */
    public function createShipment(int $orderId, string $carrier, string $service, ?string $tracking, ?string $labelRef, string $actor, ?array $qtyByLine = null): int
    {
        return $this->db->transaction(function () use ($orderId, $carrier, $service, $tracking, $labelRef, $actor, $qtyByLine) {
            $o = $this->lockOrder($orderId);
            if ($o['payment_status'] !== 'paid' || !in_array($o['fulfilment_status'], ['packed', 'partially_shipped'], true)) {
                throw new FulfilmentException('Pack the order before creating a shipment.');
            }
            if ($this->draftShipment($orderId)) {
                throw new FulfilmentException('This order already has a shipment waiting to be dispatched.');
            }
            if (trim($carrier) === '' || trim($service) === '') {
                throw new FulfilmentException('Choose a carrier and service.');
            }
            $lines = $this->db->all('SELECT id, qty, qty_shipped FROM order_lines WHERE order_id = ?', [$orderId]);
            $take = [];
            foreach ($lines as $l) {
                $left = (int) $l['qty'] - (int) $l['qty_shipped'];
                $q = $qtyByLine === null ? $left : (int) ($qtyByLine[(int) $l['id']] ?? 0);
                if ($q < 0 || $q > $left) {
                    throw new FulfilmentException("Line {$l['id']}: only {$left} units are left to ship.");
                }
                if ($q > 0) {
                    $take[(int) $l['id']] = $q;
                }
            }
            if ($take === []) {
                throw new FulfilmentException('Select at least one item to ship.');
            }
            $now = $this->clock->now();
            $tracking = $tracking === null || trim($tracking) === '' ? null : strtoupper(trim($tracking));
            $id = $this->db->insert(
                'INSERT INTO shipments (order_id, carrier, service, tracking_number, label_ref, status, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$orderId, $carrier, $service, $tracking, $labelRef, $tracking === null ? 'draft' : 'label_created', $actor, $now, $now],
            );
            foreach ($take as $lineId => $q) {
                $this->db->run('INSERT INTO shipment_lines (shipment_id, order_line_id, qty) VALUES (?, ?, ?)', [$id, $lineId, $q]);
            }
            $this->event($orderId, 'shipment.created', "Shipment created: {$carrier} {$service}" . ($tracking ? ", tracking {$tracking}" : ''), $actor);
            return $id;
        });
    }

    /** Units leave the building: the ledger deducts them, the storefront and customer are told. */
    public function dispatch(int $shipmentId, string $actor, ?string $tracking = null): void
    {
        $this->db->transaction(function () use ($shipmentId, $actor, $tracking) {
            $s = $this->db->one('SELECT * FROM shipments WHERE id = ? FOR UPDATE', [$shipmentId]) ?? throw new FulfilmentException('Unknown shipment.');
            if (in_array($s['status'], ['dispatched', 'in_transit', 'delivered', 'exception'], true)) {
                return; // already dispatched
            }
            $tracking = $tracking !== null && trim($tracking) !== '' ? strtoupper(trim($tracking)) : $s['tracking_number'];
            if ($tracking === null) {
                throw new FulfilmentException('Add the tracking number before dispatching.');
            }
            $o = $this->lockOrder((int) $s['order_id']);
            if ($o['reservation_id'] === null) {
                throw new FulfilmentException('This order has no stock reservation. Resolve the attention item first.');
            }
            $byItem = [];
            foreach ($this->db->all('SELECT ol.id, ol.inventory_item_id, sl.qty FROM shipment_lines sl JOIN order_lines ol ON ol.id = sl.order_line_id WHERE sl.shipment_id = ?', [$shipmentId]) as $l) {
                if ($l['inventory_item_id'] === null) {
                    throw new FulfilmentException('An item in this shipment is not linked to stock.');
                }
                $byItem[(int) $l['inventory_item_id']] = ($byItem[(int) $l['inventory_item_id']] ?? 0) + (int) $l['qty'];
                $this->db->run('UPDATE order_lines SET qty_shipped = qty_shipped + ? WHERE id = ?', [$l['qty'], $l['id']]);
            }
            $this->ledger->fulfil((int) $o['reservation_id'], $byItem, 'shipment:' . $shipmentId, $actor);

            $now = $this->clock->now();
            $this->db->run("UPDATE shipments SET status = 'dispatched', tracking_number = ?, dispatched_at = ?, updated_at = ? WHERE id = ?", [$tracking, $now, $now, $shipmentId]);
            $left = (int) $this->db->value('SELECT SUM(qty - qty_shipped) FROM order_lines WHERE order_id = ?', [$o['id']]);
            $this->db->run('UPDATE orders SET fulfilment_status = ?, updated_at = ? WHERE id = ?', [$left > 0 ? 'partially_shipped' : 'shipped', $now, $o['id']]);
            $this->event((int) $o['id'], 'shipment.dispatched', "Dispatched with {$s['carrier']}, tracking {$tracking}", $actor);
            $this->queue('notify.customer', "notify:{$o['id']}:dispatched:{$shipmentId}", ['order_id' => (int) $o['id'], 'event' => 'order.dispatched', 'shipment_id' => $shipmentId]);
            $this->queue('store.shipment', "store.shipment:{$shipmentId}", ['order_id' => (int) $o['id'], 'shipment_id' => $shipmentId]);
        });
    }

    /** Applies a carrier tracking event. Replays of the same event are ignored. */
    public function recordTracking(int $shipmentId, string $eventKey, string $status, ?string $description, ?string $location, \DateTimeImmutable $at): bool
    {
        return $this->db->transaction(function () use ($shipmentId, $eventKey, $status, $description, $location, $at) {
            try {
                $this->db->insert('INSERT INTO tracking_events (shipment_id, event_key, status, description, location, occurred_at) VALUES (?, ?, ?, ?, ?, ?)', [$shipmentId, $eventKey, $status, $description, $location, $at]);
            } catch (\PDOException $e) {
                if (Database::isDuplicateKey($e)) {
                    return false;
                }
                throw $e;
            }
            $map = ['in_transit' => 'in_transit', 'out_for_delivery' => 'in_transit', 'exception' => 'exception', 'delivered' => 'delivered'];
            if (isset($map[$status])) {
                $s = $this->db->one('SELECT * FROM shipments WHERE id = ? FOR UPDATE', [$shipmentId]);
                if ($s['status'] !== 'delivered') {
                    $this->db->run('UPDATE shipments SET status = ?, delivered_at = IF(? = \'delivered\', ?, delivered_at), updated_at = ? WHERE id = ?', [$map[$status], $map[$status], $at, $this->clock->now(), $shipmentId]);
                }
                if ($map[$status] === 'delivered') {
                    $this->onDelivered((int) $s['order_id']);
                }
                if ($map[$status] === 'exception') {
                    $this->db->run("UPDATE orders SET attention = COALESCE(attention, 'delivery_exception') WHERE id = ?", [$s['order_id']]);
                    $this->queue('notify.customer', "notify:{$s['order_id']}:exception:{$eventKey}", ['order_id' => (int) $s['order_id'], 'event' => 'order.delivery_exception', 'shipment_id' => $shipmentId]);
                }
                $this->event((int) $s['order_id'], 'tracking.' . $status, trim(($description ?? $status) . ($location ? " ({$location})" : '')), 'carrier:' . $s['carrier']);
            }
            return true;
        });
    }

    /** Staff confirmation of delivery when the carrier gives no delivery event. Recorded as manual. */
    public function markDelivered(int $shipmentId, string $actor): void
    {
        $this->db->transaction(function () use ($shipmentId, $actor) {
            $s = $this->db->one('SELECT * FROM shipments WHERE id = ? FOR UPDATE', [$shipmentId]) ?? throw new FulfilmentException('Unknown shipment.');
            if (!in_array($s['status'], ['dispatched', 'in_transit', 'exception'], true)) {
                throw new FulfilmentException('Only dispatched shipments can be marked delivered.');
            }
            $now = $this->clock->now();
            $this->db->run("UPDATE shipments SET status = 'delivered', delivered_at = ?, updated_at = ? WHERE id = ?", [$now, $now, $shipmentId]);
            $this->event((int) $s['order_id'], 'tracking.delivered', 'Marked delivered by staff (no carrier confirmation)', $actor);
            $this->audit->log($actor, 'shipment.mark_delivered', 'shipment', $shipmentId);
            $this->onDelivered((int) $s['order_id']);
        });
    }

    /**
     * Asks the storefront to cancel, or to record an offline payment. Nothing changes here until the
     * storefront confirms with an order snapshot. Repeated clicks queue one request.
     */
    public function requestStoreAction(int $orderId, string $action, string $actor, string $reason): void
    {
        if (!in_array($action, ['cancel', 'mark_paid'], true)) {
            throw new \InvalidArgumentException('Unknown storefront action.');
        }
        if (trim($reason) === '') {
            throw new FulfilmentException('Add a reason.');
        }
        $o = $this->order($orderId);
        $allowed = $action === 'cancel'
            ? in_array('request_cancel', $this->nextActions($orderId), true)
            : in_array('request_mark_paid', $this->nextActions($orderId), true);
        if (!$allowed) {
            throw new FulfilmentException($action === 'cancel' ? 'This order can no longer be cancelled here. Use Returns.' : 'This order is not awaiting payment.');
        }
        $this->db->transaction(function () use ($o, $action, $actor, $reason) {
            $this->queue('store.order_action', "store.order_action:{$o['id']}:{$action}", ['order_id' => (int) $o['id'], 'action' => $action, 'reason' => $reason, 'actor' => $actor]);
            $this->event((int) $o['id'], 'request.' . $action, ($action === 'cancel' ? 'Cancellation requested' : 'Offline payment recorded, waiting for the storefront') . ": {$reason}", $actor);
            $this->audit->log($actor, 'order.request_' . $action, 'order', $o['id'], ['reason' => $reason]);
        });
    }

    private function onDelivered(int $orderId): void
    {
        $open = (int) $this->db->value("SELECT COUNT(*) FROM shipments WHERE order_id = ? AND status NOT IN ('delivered','cancelled')", [$orderId]);
        $left = (int) $this->db->value('SELECT SUM(qty - qty_shipped) FROM order_lines WHERE order_id = ?', [$orderId]);
        if ($open === 0 && $left === 0) {
            $this->db->run("UPDATE orders SET fulfilment_status = 'delivered', updated_at = ? WHERE id = ?", [$this->clock->now(), $orderId]);
            $this->queue('notify.customer', "notify:{$orderId}:delivered", ['order_id' => $orderId, 'event' => 'order.delivered']);
        }
    }

    private function transition(int $orderId, array $from, string $to, string $eventType, string $message, string $actor, bool $requirePaid): void
    {
        $this->db->transaction(function () use ($orderId, $from, $to, $eventType, $message, $actor, $requirePaid) {
            $o = $this->lockOrder($orderId);
            if ($o['fulfilment_status'] === $to) {
                return;
            }
            if ($requirePaid && $o['payment_status'] !== 'paid') {
                throw new FulfilmentException('This order is not paid.');
            }
            if ($o['attention'] !== null) {
                throw new FulfilmentException('Resolve the attention item on this order first.');
            }
            if (!in_array($o['fulfilment_status'], $from, true)) {
                throw new FulfilmentException('This step is not available for the order\'s current status.');
            }
            $this->db->run('UPDATE orders SET fulfilment_status = ?, updated_at = ? WHERE id = ?', [$to, $this->clock->now(), $orderId]);
            $this->event($orderId, $eventType, $message, $actor);
            if ($to === 'processing') {
                $this->queue('notify.customer', "notify:{$orderId}:processing", ['order_id' => $orderId, 'event' => 'order.processing']);
            }
        });
    }

    private function draftShipment(int $orderId): ?int
    {
        $id = $this->db->value("SELECT id FROM shipments WHERE order_id = ? AND status IN ('draft','label_created') LIMIT 1", [$orderId]);
        return $id === null ? null : (int) $id;
    }

    private function hasUndeliveredShipment(int $orderId): bool
    {
        return (bool) $this->db->value("SELECT 1 FROM shipments WHERE order_id = ? AND status IN ('dispatched','in_transit','exception') LIMIT 1", [$orderId]);
    }

    private function order(int $id): array
    {
        return $this->db->one('SELECT * FROM orders WHERE id = ?', [$id]) ?? throw new FulfilmentException('Unknown order.');
    }

    private function lockOrder(int $id): array
    {
        return $this->db->one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$id]) ?? throw new FulfilmentException('Unknown order.');
    }

    private function event(int $orderId, string $type, string $message, string $actor): void
    {
        $this->db->run('INSERT INTO order_events (order_id, type, message, actor, created_at) VALUES (?, ?, ?, ?, ?)', [$orderId, $type, mb_substr($message, 0, 500), $actor, $this->clock->now()]);
    }

    private function queue(string $topic, string $dedupe, array $payload): void
    {
        $now = $this->clock->now();
        $this->db->run(
            "INSERT IGNORE INTO outbox (topic, dedupe_key, payload, status, next_attempt_at, created_at, updated_at) VALUES (?, ?, ?, 'pending', ?, ?, ?)",
            [$topic, $dedupe, json_encode($payload), $now, $now, $now],
        );
    }
}
