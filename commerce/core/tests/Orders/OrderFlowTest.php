<?php

declare(strict_types=1);

namespace Commerce\Core\Tests\Orders;

use Commerce\Core\Orders\Fulfilment;
use Commerce\Core\Orders\FulfilmentException;
use Commerce\Core\Orders\OrderSync;
use Commerce\Core\Orders\Returns;
use Commerce\Core\Support\Audit;
use Commerce\Core\Tests\Support\LedgerTestCase;

final class OrderFlowTest extends LedgerTestCase
{
    private OrderSync $sync;
    private Fulfilment $fulfil;
    private Returns $returns;
    private int $tee;
    private int $cap;

    public function setUp(): void
    {
        parent::setUp();
        $audit = new Audit($this->db, $this->clock);
        $this->sync = new OrderSync($this->db, $this->ledger, $this->clock);
        $this->fulfil = new Fulfilment($this->db, $this->ledger, $audit, $this->clock);
        $this->returns = new Returns($this->db, $this->ledger, $audit, $this->clock);
        $this->tee = $this->item('MI-TEE-001', 10);
        $this->cap = $this->item('MI-CAP-001', 10);
        // Each brand's store sells the shared tee; only Milan Icons sells the cap.
        foreach ([1 => 100, 2 => 200, 3 => 300] as $store => $wcId) {
            $this->list($store, $this->tee, $wcId);
        }
        $this->list(1, $this->cap, 110);
    }

    /** Checkout as the connector does it: reserve, then send the order snapshot. */
    private function checkout(int $store, int $orderId, array $lines, string $status = 'pending', string $modified = '2026-11-01T10:00:00'): array
    {
        $items = [];
        $wcLines = [];
        foreach ($lines as $i => [$item, $wcProduct, $qty, $price]) {
            $items[$item] = $qty;
            $wcLines[] = ['id' => $i + 1, 'product_id' => $wcProduct, 'variation_id' => 0, 'sku' => null, 'name' => "Item {$wcProduct}", 'quantity' => $qty, 'subtotal' => (string) ($price * $qty), 'total' => (string) ($price * $qty)];
        }
        $this->ledger->reserve($store, (string) $orderId, $items, "store:{$store}:order:{$orderId}", 1800, 172800);
        $snapshot = [
            'id' => $orderId, 'number' => (string) $orderId, 'status' => $status, 'currency' => 'INR',
            'date_created_gmt' => '2026-11-01T10:00:00', 'date_modified_gmt' => $modified,
            'total' => (string) array_sum(array_map(fn ($l) => $l['total'], $wcLines)), 'discount_total' => '0', 'shipping_total' => '0', 'total_tax' => '0',
            'payment_method' => 'razorpay', 'transaction_id' => '',
            'billing' => ['first_name' => 'Test', 'last_name' => 'Customer', 'email' => 'customer@example.test', 'country' => 'GB'],
            'shipping' => ['first_name' => 'Test', 'last_name' => 'Customer', 'country' => 'GB', 'city' => 'London'],
            'line_items' => $wcLines, 'refunds' => [], 'meta' => [],
        ];
        $this->assertSame('applied', $this->sync->ingest($store, "evt-{$store}-{$orderId}-{$status}-{$modified}", $snapshot));
        return $snapshot;
    }

    private function update(int $store, array $snapshot, string $status, string $modified, array $extra = []): string
    {
        $snapshot = array_merge($snapshot, ['status' => $status, 'date_modified_gmt' => $modified], $extra);
        return $this->sync->ingest($store, "evt-{$store}-{$snapshot['id']}-{$status}-{$modified}", $snapshot);
    }

    private function orderId(int $store, int $wcId): int
    {
        return (int) $this->db->value('SELECT id FROM orders WHERE store_id = ? AND wc_order_id = ?', [$store, $wcId]);
    }

    public function testAnOrderFromEachBrandFlowsThroughToDispatch(): void
    {
        foreach ([1 => 100, 2 => 200, 3 => 300] as $store => $wcProduct) {
            $snap = $this->checkout($store, 5000 + $store, [[$this->tee, $wcProduct, 1, 1499]]);
            $this->update($store, $snap, 'processing', '2026-11-01T10:05:00', ['transaction_id' => "pay_{$store}"]);
            $id = $this->orderId($store, 5000 + $store);
            $this->assertSame(['start_processing', 'request_cancel'], $this->fulfil->nextActions($id));
            $this->fulfil->startProcessing($id, 'user:wh');
            $this->fulfil->markPicked($id, 'user:wh');
            $this->fulfil->markPacked($id, 'user:wh');
            $this->assertSame(['create_shipment', 'request_cancel'], $this->fulfil->nextActions($id));
            $ship = $this->fulfil->createShipment($id, 'DHL Express', 'Express Worldwide', "TRK{$store}", null, 'user:wh');
            $this->assertSame(['mark_dispatched'], $this->fulfil->nextActions($id));
            $this->fulfil->dispatch($ship, 'user:wh');
            $this->fulfil->dispatch($ship, 'user:wh'); // double click
            $this->assertSame('shipped', $this->db->value('SELECT fulfilment_status FROM orders WHERE id = ?', [$id]));
        }
        $this->assertSame(7, $this->ledger->level($this->tee)->onHand, 'three shared-SKU orders from three stores, one stock record');
        $this->assertSame(3, (int) $this->db->value("SELECT COUNT(*) FROM outbox WHERE topic = 'notify.customer' AND dedupe_key LIKE '%dispatched%'"));
        $this->assertSame(149900, (int) $this->db->value("SELECT total_minor FROM orders WHERE wc_order_id = 5001"), "INR 1499 stored as 149900 paise");
    }

    public function testPaymentFailureAfterOrderCreationReturnsStock(): void
    {
        $snap = $this->checkout(1, 6001, [[$this->tee, 100, 2, 1499]]);
        $this->assertSame(8, $this->available($this->tee));
        $this->update(1, $snap, 'failed', '2026-11-01T10:03:00');
        $this->assertSame(10, $this->available($this->tee));
        $id = $this->orderId(1, 6001);
        $this->assertSame('failed', $this->db->value('SELECT payment_status FROM orders WHERE id = ?', [$id]));
        $this->assertSame([], $this->fulfil->nextActions($id));
    }

    public function testDuplicateAndOutOfOrderWebhooksAreHarmless(): void
    {
        $snap = $this->checkout(2, 6002, [[$this->tee, 200, 1, 1499]]);
        $paid = array_merge($snap, ['status' => 'processing', 'date_modified_gmt' => '2026-11-01T10:05:00']);
        $this->assertSame('applied', $this->sync->ingest(2, 'wc-delivery-77', $paid));
        $this->assertSame('duplicate', $this->sync->ingest(2, 'wc-delivery-77', $paid));
        // A delayed "pending" snapshot from before the payment arrives late.
        $this->assertSame('stale', $this->update(2, $snap, 'pending', '2026-11-01T10:01:00'));
        $id = $this->orderId(2, 6002);
        $this->assertSame('paid', $this->db->value('SELECT payment_status FROM orders WHERE id = ?', [$id]));
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM order_events WHERE order_id = ? AND type = 'payment.paid'", [$id]));
        $this->assertSame(1, $this->ledger->level($this->tee)->reserved);
    }

    public function testCancellationReleasesOnceAndIsRequestedThroughTheStorefront(): void
    {
        $snap = $this->checkout(1, 6003, [[$this->tee, 100, 1, 1499], [$this->cap, 110, 1, 999]]);
        $this->update(1, $snap, 'processing', '2026-11-01T10:05:00');
        $id = $this->orderId(1, 6003);
        $this->fulfil->requestStoreAction($id, 'cancel', 'user:support', 'Customer asked by email');
        $this->fulfil->requestStoreAction($id, 'cancel', 'user:support', 'Clicked again');
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM outbox WHERE topic = 'store.order_action'"));
        $this->assertSame('paid', $this->db->value('SELECT payment_status FROM orders WHERE id = ?', [$id]), 'nothing changes until the storefront confirms');

        $this->update(1, $snap, 'cancelled', '2026-11-01T11:00:00');
        $this->update(1, $snap, 'cancelled', '2026-11-01T11:00:01'); // second webhook
        $this->assertSame(10, $this->available($this->tee));
        $this->assertSame(10, $this->available($this->cap));
        $this->assertSame('cancelled', $this->db->value('SELECT fulfilment_status FROM orders WHERE id = ?', [$id]));
    }

    public function testUnmappedItemIsFlaggedAndBlocksFulfilment(): void
    {
        $listing = $this->catalog->recordListing(3, 999, 0, 'SCH-NEW-001', 'New thing');
        $snapshot = [
            'id' => 6004, 'number' => '6004', 'status' => 'processing', 'currency' => 'INR',
            'date_created_gmt' => '2026-11-01T10:00:00', 'date_modified_gmt' => '2026-11-01T10:00:00', 'total' => '500',
            'line_items' => [['id' => 1, 'product_id' => 999, 'variation_id' => 0, 'sku' => 'SCH-NEW-001', 'name' => 'New thing', 'quantity' => 1, 'total' => '500']],
        ];
        $this->sync->ingest(3, 'e1', $snapshot);
        $id = $this->orderId(3, 6004);
        $this->assertSame('unmapped_item', $this->db->value('SELECT attention FROM orders WHERE id = ?', [$id]));
        $this->assertSame(['resolve_attention', 'request_cancel'], $this->fulfil->nextActions($id));
        $this->assertThrows(FulfilmentException::class, fn () => $this->fulfil->startProcessing($id, 'user:wh'), 'attention');
        $this->assertSame('unmapped', $this->db->value('SELECT status FROM listings WHERE id = ?', [$listing]));

        // Staff create the SKU, link the listing, receive stock and press "Check again".
        $item = $this->item('SCH-NEW-001', 0, brandId: 3);
        $this->catalog->mapListing($listing, $item, 'user:ops', confirmSkuMismatch: true);
        $this->assertSame('paid_no_stock', $this->sync->recheck($id, 'user:ops'), 'linked, but nothing on the shelf yet');
        $this->ledger->adjust($item, \Commerce\Core\Inventory\Adjustment::Received, 3, 'user:ops');
        $this->assertNull($this->sync->recheck($id, 'user:ops'));
        $this->assertSame(['start_processing', 'request_cancel'], $this->fulfil->nextActions($id));
        $this->assertSame(1, $this->ledger->level($item)->reserved);
    }

    public function testPaidOrderWithoutCheckoutHoldReservesOrFlagsShortStock(): void
    {
        $base = ['currency' => 'INR', 'date_created_gmt' => '2026-11-01T10:00:00', 'date_modified_gmt' => '2026-11-01T10:00:00', 'status' => 'processing'];
        $this->sync->ingest(1, 'a', $base + ['id' => 7001, 'total' => '9000', 'line_items' => [['id' => 1, 'product_id' => 110, 'variation_id' => 0, 'name' => 'Cap', 'quantity' => 9, 'total' => '9000']]]);
        $this->assertSame(1, $this->available($this->cap));
        $this->sync->ingest(1, 'b', $base + ['id' => 7002, 'total' => '2000', 'line_items' => [['id' => 1, 'product_id' => 110, 'variation_id' => 0, 'name' => 'Cap', 'quantity' => 2, 'total' => '2000']]]);
        $this->assertSame('paid_no_stock', $this->db->value('SELECT attention FROM orders WHERE wc_order_id = 7002'));
        $this->assertSame(1, $this->available($this->cap), 'flagged, not oversold');
    }

    public function testPartialShipmentThenTheRest(): void
    {
        $snap = $this->checkout(1, 6005, [[$this->tee, 100, 2, 1499], [$this->cap, 110, 1, 999]]);
        $this->update(1, $snap, 'processing', '2026-11-01T10:05:00');
        $id = $this->orderId(1, 6005);
        $this->fulfil->startProcessing($id, 'u');
        $this->fulfil->markPicked($id, 'u');
        $this->fulfil->markPacked($id, 'u');
        $teeLine = (int) $this->db->value('SELECT id FROM order_lines WHERE order_id = ? AND inventory_item_id = ?', [$id, $this->tee]);
        $s1 = $this->fulfil->createShipment($id, 'DHL Express', 'Express Worldwide', 'A1', null, 'u', [$teeLine => 2]);
        $this->fulfil->dispatch($s1, 'u');
        $this->assertSame('partially_shipped', $this->db->value('SELECT fulfilment_status FROM orders WHERE id = ?', [$id]));
        $this->assertSame(['create_shipment'], $this->fulfil->nextActions($id));
        $s2 = $this->fulfil->createShipment($id, 'DHL Express', 'Express Worldwide', null, null, 'u');
        $this->assertThrows(FulfilmentException::class, fn () => $this->fulfil->dispatch($s2, 'u'), 'tracking number');
        $this->fulfil->dispatch($s2, 'u', 'a2');
        $this->assertSame('shipped', $this->db->value('SELECT fulfilment_status FROM orders WHERE id = ?', [$id]));
        $this->assertSame('fulfilled', $this->ledger->reservation((int) $this->db->value('SELECT reservation_id FROM orders WHERE id = ?', [$id]))['state']);
    }

    public function testTrackingEventsAreDedupedAndDeliveryCompletesTheOrder(): void
    {
        $snap = $this->checkout(3, 6006, [[$this->tee, 300, 1, 1499]]);
        $this->update(3, $snap, 'processing', '2026-11-01T10:05:00');
        $id = $this->orderId(3, 6006);
        foreach (['startProcessing', 'markPicked', 'markPacked'] as $step) {
            $this->fulfil->{$step}($id, 'u');
        }
        $ship = $this->fulfil->createShipment($id, 'FedEx', 'International Priority', 'FX1', null, 'u');
        $this->fulfil->dispatch($ship, 'u');
        $at = new \DateTimeImmutable('2026-11-02 08:00:00', new \DateTimeZone('UTC'));
        $this->assertTrue($this->fulfil->recordTracking($ship, 'ev-1', 'exception', 'Address incomplete', 'Heathrow', $at));
        $this->assertFalse($this->fulfil->recordTracking($ship, 'ev-1', 'exception', 'Address incomplete', 'Heathrow', $at));
        $this->assertSame('delivery_exception', $this->db->value('SELECT attention FROM orders WHERE id = ?', [$id]));
        $this->fulfil->recordTracking($ship, 'ev-2', 'delivered', 'Delivered', 'London', $at->modify('+1 day'));
        $this->assertSame('delivered', $this->db->value('SELECT fulfilment_status FROM orders WHERE id = ?', [$id]));
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM outbox WHERE dedupe_key = 'notify:{$id}:delivered'"));
    }

    public function testReturnedItemsDamagedOrApprovedForResaleAndRefundRequestedOnce(): void
    {
        $snap = $this->checkout(1, 6007, [[$this->tee, 100, 3, 1499]]);
        $this->update(1, $snap, 'processing', '2026-11-01T10:05:00');
        $id = $this->orderId(1, 6007);
        foreach (['startProcessing', 'markPicked', 'markPacked'] as $step) {
            $this->fulfil->{$step}($id, 'u');
        }
        $this->fulfil->dispatch($this->fulfil->createShipment($id, 'DHL Express', 'Express Worldwide', 'R1', null, 'u'), 'u');
        $this->assertSame(7, $this->ledger->level($this->tee)->onHand);

        $line = (int) $this->db->value('SELECT id FROM order_lines WHERE order_id = ?', [$id]);
        $this->assertThrows(FulfilmentException::class, fn () => $this->returns->open($id, [$line => 4], 'Too big', 'return', 'user:support'), '3 units can be returned');
        $ret = $this->returns->open($id, [$line => 3], 'Too big', 'return', 'user:support');
        $rl = (int) $this->db->value('SELECT id FROM return_lines WHERE return_id = ?', [$ret]);

        $this->returns->receive($rl, 2, 'user:wh');
        $this->returns->receive($rl, 2, 'user:wh'); // same total again: no change
        $this->assertSame(9, $this->ledger->level($this->tee)->onHand);
        $this->assertSame(7, $this->available($this->tee), 'received but not yet sellable');

        $this->returns->inspect($rl, 1, 1, 'user:wh');
        $this->assertSame(8, $this->available($this->tee));
        $this->assertSame(1, $this->ledger->level($this->tee)->damaged);
        $this->assertThrows(FulfilmentException::class, fn () => $this->returns->inspect($rl, 2, 1, 'user:wh'), 'Only 2');

        $this->returns->requestRefund($ret, '2998.00', 'user:finance');
        $this->assertThrows(FulfilmentException::class, fn () => $this->returns->requestRefund($ret, '1.00', 'user:finance'), 'already been requested');
        $this->assertSame('requested', $this->db->value('SELECT refund_status FROM returns WHERE id = ?', [$ret]), 'not marked refunded locally');
        $this->returns->recordRefundResult($ret, true, 'rfnd_1', null);
        $this->returns->recordRefundResult($ret, true, 'rfnd_1', null);
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM order_events WHERE type = 'refund.succeeded'"));
    }

    public function testFailedEventIsRecordedAndRetried(): void
    {
        $bad = ['id' => 8001, 'status' => 'processing', 'currency' => 'XYZ', 'date_created_gmt' => '2026-11-01T10:00:00', 'total' => '1', 'line_items' => []];
        $this->assertThrows(\InvalidArgumentException::class, fn () => $this->sync->ingest(1, 'bad-1', $bad), 'XYZ');
        $row = $this->db->one("SELECT * FROM inbox_events WHERE event_key = 'bad-1'");
        $this->assertSame('failed', $row['status']);
        $this->assertSame('duplicate', $this->sync->ingest(1, 'bad-1', $bad), 'a redelivery does not double process');
        // Fix the stored payload (as support would after correcting the currency setting) and retry.
        $this->db->run("UPDATE inbox_events SET payload = REPLACE(payload, 'XYZ', 'INR') WHERE id = ?", [$row['id']]);
        $this->assertSame('applied', $this->sync->retry((int) $row['id']));
        $this->assertSame('processed', $this->db->value('SELECT status FROM inbox_events WHERE id = ?', [$row['id']]));
    }
}
