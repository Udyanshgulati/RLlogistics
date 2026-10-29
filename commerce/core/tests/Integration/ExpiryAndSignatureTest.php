<?php

declare(strict_types=1);

namespace Commerce\Core\Tests\Integration;

use Commerce\Core\Integration\Signature;
use Commerce\Core\Inventory\Adjustment;
use Commerce\Core\Inventory\ExpiryWorker;
use Commerce\Core\Inventory\LedgerException;
use Commerce\Core\Tests\Support\FakeStoreClient;
use Commerce\Core\Tests\Support\LedgerTestCase;

final class ExpiryAndSignatureTest extends LedgerTestCase
{
    public function testDueHoldsAreCheckedWithTheStorefrontBeforeRelease(): void
    {
        $item = $this->item(qty: 10);
        $store = new FakeStoreClient();
        $ids = [];
        foreach (['101' => 'processing', '102' => 'on-hold', '103' => 'pending', '104' => null] as $order => $status) {
            $ids[$order] = $this->ledger->reserve(1, (string) $order, [$item => 1], "k{$order}", 900, 7200)->reservationId;
            if ($status !== null) {
                $store->orderStatuses["1:{$order}"] = $status;
            }
        }
        $this->clock->advance(901);
        $worker = new ExpiryWorker($this->db, $this->ledger, $store, $this->clock);
        $this->assertSame(['confirmed' => 1, 'extended' => 1, 'expired' => 2, 'kept' => 0, 'forced' => 0], $worker->run());
        $this->assertSame('confirmed', $this->ledger->reservation($ids['101'])['state'], 'paid but the webhook was lost: kept');
        $this->assertSame('held', $this->ledger->reservation($ids['102'])['state']);
        $this->assertSame(8, $this->available($item));
    }

    public function testUnreachableStorefrontKeepsTheHoldUntilTheHardLimit(): void
    {
        $item = $this->item(qty: 1);
        $id = $this->ledger->reserve(1, '201', [$item => 1], 'k', 900, 3600)->reservationId;
        $store = new FakeStoreClient();
        $store->failNext = 100;
        $worker = new ExpiryWorker($this->db, $this->ledger, $store, $this->clock);
        $this->clock->advance(901);
        $this->assertSame(1, $worker->run()['kept']);
        $this->assertSame(0, $this->available($item), 'not released on a guess');
        $this->clock->advance(3600);
        $this->assertSame(1, $worker->run()['forced']);
        $this->assertSame('expired_store_unreachable', $this->ledger->reservation($id)['exception']);
        $this->assertSame(1, $this->available($item));
    }

    public function testAdjustmentUndoIsAReversingEntryAllowedOnce(): void
    {
        $item = $this->item(qty: 5);
        $this->ledger->adjust($item, Adjustment::Damaged, 2, 'user:1', 'Wet box');
        $mid = (int) $this->db->value('SELECT MAX(id) FROM stock_movements');
        $this->assertThrows(LedgerException::class, fn () => $this->ledger->reverseAdjustment($mid, 'user:2'), 'person who made');
        $this->ledger->reverseAdjustment($mid, 'user:1');
        $this->assertSame(0, $this->ledger->level($item)->damaged);
        $this->assertThrows(LedgerException::class, fn () => $this->ledger->reverseAdjustment($mid, 'user:1'), 'already been undone');
        $this->ledger->adjust($item, Adjustment::Received, 1, 'user:1');
        $later = (int) $this->db->value('SELECT MAX(id) FROM stock_movements');
        $this->clock->advance(601);
        $this->assertThrows(LedgerException::class, fn () => $this->ledger->reverseAdjustment($later, 'user:1'), 'too old');
    }

    public function testHubSignatures(): void
    {
        $sig = Signature::sign('s3cret', 1_700_000_000, 'POST', '/api/v1/reservations', '{"a":1}');
        $this->assertTrue(Signature::verify('s3cret', '1700000000', $sig, 'POST', '/api/v1/reservations', '{"a":1}', 1_700_000_100));
        $this->assertFalse(Signature::verify('s3cret', '1700000000', $sig, 'POST', '/api/v1/reservations', '{"a":2}', 1_700_000_100), 'tampered body');
        $this->assertFalse(Signature::verify('s3cret', '1700000000', $sig, 'POST', '/api/v1/reservations', '{"a":1}', 1_700_000_400), 'too old');
        $this->assertFalse(Signature::verify('', '1700000000', $sig, 'POST', '/api/v1/reservations', '{"a":1}', 1_700_000_100), 'no secret configured');
        $woo = base64_encode(hash_hmac('sha256', '{"id":1}', 'whsec', true));
        $this->assertTrue(Signature::verifyWooWebhook('whsec', $woo, '{"id":1}'));
        $this->assertFalse(Signature::verifyWooWebhook('whsec', $woo, '{"id":2}'));
    }
}
