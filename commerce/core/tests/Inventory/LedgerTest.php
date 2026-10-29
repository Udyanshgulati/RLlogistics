<?php

declare(strict_types=1);

namespace Commerce\Core\Tests\Inventory;

use Commerce\Core\Inventory\Adjustment;
use Commerce\Core\Inventory\IdempotencyConflict;
use Commerce\Core\Inventory\InsufficientStock;
use Commerce\Core\Inventory\LedgerException;
use Commerce\Core\Tests\Support\LedgerTestCase;

final class LedgerTest extends LedgerTestCase
{
    public function testAdjustmentsRecordPreviousAndResultingQuantities(): void
    {
        $item = $this->item(qty: 25);
        $this->ledger->adjust($item, Adjustment::Damaged, 2, 'user:7', 'Torn seam');
        $this->ledger->adjust($item, Adjustment::Lost, 1, 'user:7', 'Missing at count');
        $this->ledger->adjust($item, Adjustment::Correction, -3, 'user:7', 'Double-entered delivery');

        $level = $this->ledger->level($item);
        $this->assertSame(21, $level->onHand);
        $this->assertSame(2, $level->damaged);
        $this->assertSame(19, $level->available());

        $last = $this->db->one('SELECT * FROM stock_movements WHERE inventory_item_id = ? ORDER BY id DESC LIMIT 1', [$item]);
        $this->assertSame('adjust.correction', $last['type']);
        $this->assertSame(-3, (int) $last['d_on_hand']);
        $this->assertSame(21, (int) $last['on_hand_after']); // resulting
        $this->assertSame(24, (int) $last['on_hand_after'] - (int) $last['d_on_hand']); // previous
        $this->assertSame('user:7', $last['actor']);
        $this->assertSame('Double-entered delivery', $last['reason']);
    }

    public function testPreviewShowsResultWithoutWriting(): void
    {
        $item = $this->item(qty: 25);
        $p = $this->ledger->preview($item, Adjustment::Received, 10);
        $this->assertTrue($p['allowed']);
        $this->assertSame(35, $p['after']->onHand);
        $this->assertSame(25, $this->ledger->level($item)->onHand);

        $p = $this->ledger->preview($item, Adjustment::Lost, 30);
        $this->assertFalse($p['allowed']);
        $this->assertStringContains('Only 25 units are on hand', $p['problem']);
    }

    public function testReasonRequiredForLossCorrectionAndOther(): void
    {
        $item = $this->item(qty: 5);
        $this->assertThrows(LedgerException::class, fn () => $this->ledger->adjust($item, Adjustment::Lost, 1, 'u'), 'reason');
        $this->assertThrows(LedgerException::class, fn () => $this->ledger->adjust($item, Adjustment::Received, -1, 'u'), 'greater than zero');
        $this->ledger->adjust($item, Adjustment::Received, 1, 'u'); // reason optional
        $this->assertSame(6, $this->ledger->level($item)->onHand);
    }

    public function testCannotRemoveStockThatIsReserved(): void
    {
        $item = $this->item(qty: 3);
        $this->ledger->reserve(1, '1001', [$item => 2], 'k1', 900, 3600);
        $this->assertThrows(InsufficientStock::class, fn () => $this->ledger->adjust($item, Adjustment::Lost, 2, 'u', 'gone'));
        $this->ledger->adjust($item, Adjustment::Lost, 1, 'u', 'gone');
        $this->assertSame(0, $this->available($item));
    }

    public function testReserveIsAllOrNothing(): void
    {
        $a = $this->item('MI-TEE-001', 5);
        $b = $this->item('MI-TEE-002', 1);
        $e = $this->assertThrows(InsufficientStock::class, fn () => $this->ledger->reserve(1, '1001', [$a => 2, $b => 2], 'k1', 900, 3600));
        $this->assertSame([$b => ['requested' => 2, 'available' => 1]], $e->shortfalls);
        $this->assertSame(5, $this->available($a), 'first line rolled back');
        $this->assertNull($this->ledger->reservationByKey('k1'));
    }

    public function testRetriedCheckoutReplaysTheSameReservation(): void
    {
        $item = $this->item(qty: 5);
        $first = $this->ledger->reserve(1, '1001', [$item => 2], 'store:1:order:1001', 900, 3600);
        $again = $this->ledger->reserve(1, '1001', [$item => 2], 'store:1:order:1001', 900, 3600);
        $this->assertSame($first->reservationId, $again->reservationId);
        $this->assertTrue($again->replayed);
        $this->assertSame(3, $this->available($item), 'reserved once');
        $this->assertThrows(IdempotencyConflict::class, fn () => $this->ledger->reserve(1, '1001', [$item => 3], 'store:1:order:1001', 900, 3600));
    }

    public function testPaymentFailureReleasesOnce(): void
    {
        $item = $this->item(qty: 5);
        $r = $this->ledger->reserve(1, '1001', [$item => 2], 'k', 900, 3600);
        $this->assertSame('released', $this->ledger->release($r->reservationId, 'Payment failed', 'store:1')->state);
        $twice = $this->ledger->release($r->reservationId, 'Order cancelled', 'store:1');
        $this->assertTrue($twice->replayed);
        $this->assertSame(5, $this->available($item));
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM stock_movements WHERE type = 'release'"));
    }

    public function testDuplicatePaymentWebhookConfirmsOnce(): void
    {
        $item = $this->item(qty: 5);
        $r = $this->ledger->reserve(1, '1001', [$item => 1], 'k', 900, 3600);
        $this->assertFalse($this->ledger->confirm($r->reservationId, 'pay_1', 'webhook')->replayed);
        $this->assertTrue($this->ledger->confirm($r->reservationId, 'pay_1', 'webhook')->replayed);
        $this->assertSame(4, $this->available($item));
        $this->assertSame([], $this->ledger->dueForExpiry(), 'confirmed holds never expire');
    }

    public function testUnpaidHoldExpiresOnlyWhenDue(): void
    {
        $item = $this->item(qty: 1);
        $r = $this->ledger->reserve(1, '1001', [$item => 1], 'k', 900, 3600);
        $this->assertFalse($this->ledger->expire($r->reservationId, 'system'), 'not due yet');
        $this->clock->advance(901);
        $this->assertSame([$r->reservationId], $this->ledger->dueForExpiry());
        $this->assertTrue($this->ledger->expire($r->reservationId, 'system'));
        $this->assertFalse($this->ledger->expire($r->reservationId, 'system'), 'expires once');
        $this->assertSame(1, $this->available($item));
    }

    public function testExtendIsCappedAtTheHardLimit(): void
    {
        $item = $this->item(qty: 1);
        $r = $this->ledger->reserve(1, '1001', [$item => 1], 'k', 900, 1800);
        $this->ledger->extend($r->reservationId, 7200, 'store:1');
        $this->clock->advance(1799);
        $this->assertSame([], $this->ledger->dueForExpiry());
        $this->clock->advance(1);
        $this->assertSame([$r->reservationId], $this->ledger->dueForExpiry());
    }

    public function testLatePaymentReacquiresStockWhenStillAvailable(): void
    {
        $item = $this->item(qty: 1);
        $r = $this->ledger->reserve(1, '1001', [$item => 1], 'k', 900, 3600);
        $this->clock->advance(1000);
        $this->ledger->expire($r->reservationId, 'system');
        $res = $this->ledger->confirm($r->reservationId, 'pay_late', 'webhook');
        $this->assertTrue($res->ok);
        $this->assertSame('confirmed', $res->state);
        $this->assertSame(0, $this->available($item));
    }

    public function testLatePaymentWithoutStockIsFlaggedNotOversold(): void
    {
        $item = $this->item(qty: 1);
        $r = $this->ledger->reserve(1, '1001', [$item => 1], 'k1', 900, 3600);
        $this->clock->advance(1000);
        $this->ledger->expire($r->reservationId, 'system');
        $this->ledger->reserve(3, '2001', [$item => 1], 'k2', 900, 3600); // someone on Schermo takes the unit
        $res = $this->ledger->confirm($r->reservationId, 'pay_late', 'webhook');
        $this->assertFalse($res->ok);
        $this->assertSame('paid_after_expiry_no_stock', $res->exception);
        $this->assertSame(0, $this->available($item));
        $this->assertSame(1, $this->ledger->level($item)->reserved, 'never more reserved than on hand');
    }

    public function testPaymentAfterCancellationNeedsAPerson(): void
    {
        $item = $this->item(qty: 1);
        $r = $this->ledger->reserve(1, '1001', [$item => 1], 'k', 900, 3600);
        $this->ledger->release($r->reservationId, 'Cancelled by customer', 'store:1');
        $res = $this->ledger->confirm($r->reservationId, 'pay_1', 'webhook');
        $this->assertSame('paid_after_release', $res->exception);
        $this->assertSame(1, $this->available($item));
    }

    public function testSharedSkuAcrossThreeStoresDeductsFromOneRecord(): void
    {
        // The brief's example: 100 units; Milan Icons 2, Significant Sports 3, Schermo 1 => 94 left.
        $item = $this->item('MI-TEE-010', 100);
        $orders = [[1, 2], [2, 3], [3, 1]];
        foreach ($orders as $i => [$store, $q]) {
            $r = $this->ledger->reserve($store, "o{$i}", [$item => $q], "s{$store}:o{$i}", 900, 3600);
            $this->ledger->confirm($r->reservationId, "pay{$i}", 'webhook');
            $this->ledger->fulfil($r->reservationId, [$item => $q], "shp{$i}", 'user:warehouse');
        }
        $level = $this->ledger->level($item);
        $this->assertSame(94, $level->onHand);
        $this->assertSame(0, $level->reserved);
        $this->assertSame(94, $level->available());
    }

    public function testPartialFulfilmentThenCancellationReleasesOnlyTheRest(): void
    {
        $a = $this->item('MI-TEE-001', 5);
        $b = $this->item('MI-TEE-002', 5);
        $r = $this->ledger->reserve(1, '1001', [$a => 2, $b => 1], 'k', 900, 3600);
        $this->assertThrows(LedgerException::class, fn () => $this->ledger->fulfil($r->reservationId, [$a => 1], 'shp1', 'u'), 'not paid');
        $this->ledger->confirm($r->reservationId, 'pay', 'webhook');
        $this->assertSame('partially_fulfilled', $this->ledger->fulfil($r->reservationId, [$a => 2], 'shp1', 'u')->state);
        $this->assertTrue($this->ledger->fulfil($r->reservationId, [$a => 2], 'shp1', 'u')->replayed, 'same shipment twice');
        $this->assertThrows(LedgerException::class, fn () => $this->ledger->fulfil($r->reservationId, [$a => 1], 'shp2', 'u'), 'only 0');

        $this->assertSame('fulfilled', $this->ledger->release($r->reservationId, 'Customer cancelled the rest', 'u')->state);
        $this->assertSame(3, $this->ledger->level($a)->onHand);
        $this->assertSame(5, $this->available($b));
    }

    public function testReturnedItemsAreNotSellableUntilInspected(): void
    {
        $item = $this->item(qty: 1);
        $this->ledger->receiveReturn($item, 2, 'user:1', 'RMA-1');
        $this->assertSame(3, $this->ledger->level($item)->onHand);
        $this->assertSame(1, $this->available($item), 'awaiting inspection is not available');
        $this->ledger->inspectReturn($item, 1, true, 'user:1', 'RMA-1');
        $this->ledger->inspectReturn($item, 1, false, 'user:1', 'RMA-1');
        $level = $this->ledger->level($item);
        $this->assertSame(2, $level->available());
        $this->assertSame(1, $level->damaged);
        $this->assertSame(0, $level->quarantine);
        $this->assertThrows(LedgerException::class, fn () => $this->ledger->inspectReturn($item, 1, true, 'u', 'RMA-1'), 'awaiting inspection');
    }

    public function testStocktakeRecordsTheDifferenceAndRespectsReservations(): void
    {
        $item = $this->item(qty: 10);
        $this->ledger->reserve(1, '1001', [$item => 4], 'k', 900, 3600);
        $this->assertSame(8, $this->ledger->stocktake($item, 8, 'user:2')->onHand);
        $this->assertSame(-2, (int) $this->db->value("SELECT d_on_hand FROM stock_movements WHERE type = 'stocktake'"));
        $this->assertThrows(InsufficientStock::class, fn () => $this->ledger->stocktake($item, 3, 'user:2'));
    }

    public function testDamagedWriteOffAndPurchaseReceipt(): void
    {
        $item = $this->item(qty: 0);
        $this->ledger->receivePurchase($item, 18, 2, 'user:3', 'PO-1');
        $this->assertSame(20, $this->ledger->level($item)->onHand);
        $this->assertSame(18, $this->available($item));
        $this->ledger->writeOffDamaged($item, 2, 'user:3', 'Disposed');
        $this->assertSame(18, $this->ledger->level($item)->onHand);
        $this->assertSame(0, $this->ledger->level($item)->damaged);
    }

    public function testLowStockAlertsAreSentOncePerCondition(): void
    {
        $item = $this->item(qty: 12, reorder: 10);
        $alerts = fn () => (int) $this->db->value("SELECT COUNT(*) FROM outbox WHERE topic = 'alert.stock'");
        $this->ledger->adjust($item, Adjustment::Lost, 3, 'u', 'x'); // 9 < 10: low
        $this->ledger->adjust($item, Adjustment::Lost, 1, 'u', 'x'); // 8: still low, no new alert
        $this->assertSame(1, $alerts());
        $this->ledger->adjust($item, Adjustment::Lost, 8, 'u', 'x'); // 0: out of stock
        $this->assertSame(2, $alerts());
        $this->ledger->adjust($item, Adjustment::Received, 20, 'u'); // recovered
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM low_stock_alerts WHERE resolved_at IS NULL'));
        $this->ledger->adjust($item, Adjustment::Lost, 15, 'u', 'x'); // low again: a new condition
        $this->assertSame(3, $alerts());
    }

    public function testStockPushesAreCoalescedPerListing(): void
    {
        $item = $this->item(qty: 10);
        $this->list(1, $item, 501);
        $this->list(3, $item, 901);
        $this->db->run("DELETE FROM outbox WHERE topic = 'stock.push'");
        for ($i = 0; $i < 5; $i++) {
            $this->ledger->adjust($item, Adjustment::Received, 1, 'u');
        }
        $this->assertSame(2, (int) $this->db->value("SELECT COUNT(*) FROM outbox WHERE topic = 'stock.push' AND status = 'pending'"));
    }

    public function testMovementsAreAppendOnly(): void
    {
        $item = $this->item(qty: 1);
        $this->assertThrows(\PDOException::class, fn () => $this->db->run('UPDATE stock_movements SET d_on_hand = 100'), 'append-only');
        $this->assertThrows(\PDOException::class, fn () => $this->db->run('DELETE FROM stock_movements'), 'append-only');
        $this->assertSame(1, $this->ledger->level($item)->onHand);
    }

    public function testDatabaseRejectsDirectOversellEvenOutsideTheLedger(): void
    {
        $item = $this->item(qty: 1);
        $this->assertThrows(\PDOException::class, fn () => $this->db->run('UPDATE stock_levels SET reserved = 2 WHERE inventory_item_id = ?', [$item]));
    }

    public function testFailedReservationInsideAnOuterTransactionLeavesNothingBehind(): void
    {
        $item = $this->item(qty: 1);
        $this->db->transaction(function () use ($item) {
            try {
                $this->ledger->reserve(1, 'o1', [$item => 5], 'k-nested', 900, 3600);
            } catch (InsufficientStock) {
            }
        });
        $this->assertNull($this->ledger->reservationByKey('k-nested'), 'the savepoint rolled back the half-written reservation');
        $this->assertSame(1, $this->available($item));
    }
}
