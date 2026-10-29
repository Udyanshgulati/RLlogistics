<?php

declare(strict_types=1);

namespace Commerce\Core\Tests\Integration;

use Commerce\Core\Integration\Outbox;
use Commerce\Core\Integration\PermanentFailure;
use Commerce\Core\Integration\StockPushHandler;
use Commerce\Core\Integration\StoreRequestHandlers;
use Commerce\Core\Integration\WooCommerceClient;
use Commerce\Core\Inventory\Adjustment;
use Commerce\Core\Orders\Returns;
use Commerce\Core\Support\Audit;
use Commerce\Core\Tests\Support\FakeStoreClient;
use Commerce\Core\Tests\Support\LedgerTestCase;

final class OutboxTest extends LedgerTestCase
{
    private FakeStoreClient $store;
    private Outbox $outbox;

    public function setUp(): void
    {
        parent::setUp();
        $this->store = new FakeStoreClient();
        $returns = new Returns($this->db, $this->ledger, new Audit($this->db, $this->clock), $this->clock);
        $this->outbox = new Outbox($this->db, $this->clock, ['stock.push' => new StockPushHandler($this->db, $this->store)] + (new StoreRequestHandlers($this->db, $this->store, $returns))->handlers());
    }

    public function testStockPushSendsLatestAvailabilityToEveryStoreSellingTheItem(): void
    {
        $item = $this->item('MI-TEE-001', 10);
        $this->list(1, $item, 100);
        $this->list(3, $item, 300);
        $this->ledger->reserve(1, 'o1', [$item => 3], 'k', 900, 3600);
        $this->outbox->runOnce();
        $this->assertSame(['1:100:0' => 7, '3:300:0' => 7], $this->store->stock);
    }

    public function testStorefrontOutageIsRetriedWithBackoffThenSucceeds(): void
    {
        $item = $this->item('MI-TEE-001', 5);
        $this->list(2, $item, 200);
        $this->store->failNext = 2;
        $this->assertSame(['done' => 0, 'failed' => 1], $this->outbox->runOnce());
        $this->assertSame(['done' => 0, 'failed' => 0], $this->outbox->runOnce(), 'not due again yet');
        $this->clock->advance(61);
        $this->assertSame(['done' => 0, 'failed' => 1], $this->outbox->runOnce());
        $this->clock->advance(119);
        $this->assertSame(['done' => 0, 'failed' => 0], $this->outbox->runOnce(), 'second backoff is 2 minutes');
        $this->clock->advance(2);
        $this->ledger->adjust($item, Adjustment::Received, 1, 'u'); // a change during the outage queues a fresh push
        $this->outbox->runOnce();
        $this->assertSame(['2:200:0' => 6], $this->store->stock, 'the latest quantity, not the one at failure time');
        $this->assertSame(0, (int) $this->db->value("SELECT COUNT(*) FROM outbox WHERE status IN ('pending','processing','dead')"));
    }

    public function testPersistentFailureBecomesDeadAndCanBeRetriedManually(): void
    {
        $item = $this->item('MI-TEE-001', 5);
        $this->list(1, $item, 100);
        $this->store->failNext = 100;
        for ($i = 0; $i < Outbox::MAX_ATTEMPTS; $i++) {
            $this->outbox->runOnce();
            $this->clock->advance(3600);
        }
        $dead = $this->db->one("SELECT * FROM outbox WHERE status = 'dead'");
        $this->assertSame('Could not reach the storefront: timed out', $dead['last_error']);
        $this->store->failNext = 0;
        $this->outbox->retry((int) $dead['id']);
        $this->outbox->runOnce();
        $this->assertSame(5, $this->store->stock['1:100:0']);
    }

    public function testWorkerThatDiesMidJobIsRecoveredAfterTheLease(): void
    {
        $item = $this->item('MI-TEE-001', 5);
        $this->list(1, $item, 100);
        $this->db->run("UPDATE outbox SET status = 'processing', locked_until = ?", [$this->clock->now()->modify('+120 seconds')]);
        $this->assertSame(['done' => 0, 'failed' => 0], $this->outbox->runOnce());
        $this->clock->advance(121);
        $this->assertSame(['done' => 1, 'failed' => 0], $this->outbox->runOnce());
    }

    public function testUnlinkedListingIsPushedAsZero(): void
    {
        $item = $this->item('MI-TEE-001', 5);
        $listing = $this->list(1, $item, 100);
        $this->outbox->runOnce();
        $this->catalog->unmapListing($listing, 'u', 'Wrong product');
        $this->outbox->runOnce();
        $this->assertSame(0, $this->store->stock['1:100:0']);
    }

    public function testLostRefundResponseNeverRefundsTwice(): void
    {
        $this->db->run("INSERT INTO orders (id, store_id, wc_order_id, order_number, placed_at, wc_status, payment_status, currency, total_minor, source_modified_at, created_at, updated_at)
                        VALUES (1, 1, 900, '900', NOW(6), 'completed', 'paid', 'USD', 5000, NOW(6), NOW(6), NOW(6))");
        $this->db->run("INSERT INTO returns (id, order_id, rma_number, reason, refund_status, created_by, created_at, updated_at) VALUES (1, 1, 'RMA-000001', 'Too small', 'requested', 'u', NOW(6), NOW(6))");
        $this->db->run("INSERT INTO outbox (topic, dedupe_key, payload, status, next_attempt_at, created_at, updated_at) VALUES ('store.refund', 'store.refund:return:1', ?, 'pending', ?, ?, ?)",
            [json_encode(['return_id' => 1, 'order_id' => 1, 'amount_minor' => 2500, 'currency' => 'USD']), $this->clock->now(), $this->clock->now(), $this->clock->now()]);
        $this->store->loseRefundResponse = true;
        $this->outbox->runOnce();
        $this->assertSame('requested', $this->db->value('SELECT refund_status FROM returns WHERE id = 1'));
        $this->clock->advance(61);
        $this->outbox->runOnce();
        $this->assertSame(1, $this->store->refundCalls, 'the retry found the refund instead of making another');
        $this->assertSame('25.00', $this->store->refunds[0]['amount']);
        $this->assertSame('succeeded', $this->db->value('SELECT refund_status FROM returns WHERE id = 1'));
    }

    public function testWooClientRefusesUnsafeOrUnconfiguredCalls(): void
    {
        $calls = [];
        $transport = function (string $method, string $url, array $headers, ?array $body) use (&$calls) {
            $calls[] = [$method, $url, $body];
            return str_contains($url, 'products/5') ? [401, ['message' => 'Invalid signature consumer_key=ck_abcdef0123456789']] : [503, null];
        };
        $client = new WooCommerceClient($this->db, transport: $transport);
        $this->assertThrows(PermanentFailure::class, fn () => $client->setStock(1, 5, 0, 1), 'no HTTPS address'); // Milan Icons domain not confirmed
        $this->assertThrows(PermanentFailure::class, fn () => $client->setStock(3, 5, 0, 1), 'SCHERMO_WC_KEY');
        putenv('STORE_SCHERMO_WC_KEY=ck_test');
        putenv('STORE_SCHERMO_WC_SECRET=cs_test');
        try {
            $e = $this->assertThrows(PermanentFailure::class, fn () => $client->setStock(3, 5, 0, 1), '401');
            $this->assertStringContains('[redacted]', Outbox::safeMessage($e));
            $this->assertThrows(\RuntimeException::class, fn () => $client->setStock(3, 6, 7, 1), '503');
            $this->assertSame(['PUT', 'https://schermo.in/wp-json/wc/v3/products/6/variations/7', ['manage_stock' => true, 'stock_quantity' => 1]], $calls[1]);
        } finally {
            putenv('STORE_SCHERMO_WC_KEY');
            putenv('STORE_SCHERMO_WC_SECRET');
        }
    }
}
