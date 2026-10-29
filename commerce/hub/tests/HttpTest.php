<?php

declare(strict_types=1);

namespace Commerce\Hub\Tests;

use Commerce\Core\Catalog\SkuPolicy;
use Commerce\Core\Inventory\Adjustment;
use Commerce\Core\Integration\Signature;
use Commerce\Core\Support\FixedClock;
use Commerce\Core\Tests\Support\TestCase;
use Commerce\Core\Tests\Support\TestDb;
use Commerce\Hub\App;
use Commerce\Hub\Controllers\AuthController;
use Commerce\Hub\Http\ArraySession;
use Commerce\Hub\Http\Request;
use Commerce\Hub\Http\Response;

/** End-to-end through the real router, session, CSRF, permission checks and database. */
final class HttpTest extends TestCase
{
    private \Commerce\Core\Db\Database $db;
    private FixedClock $clock;
    private ArraySession $session;
    private App $app;
    private array $env = [
        'APP_ENV' => 'testing', 'TESTING' => '1',
        'STORE_SCHERMO_HUB_SECRET' => 'hub-secret-schermo', 'STORE_MILAN_ICONS_HUB_SECRET' => 'hub-secret-mi',
        'STORE_SCHERMO_WEBHOOK_SECRET' => 'wh-secret-schermo',
    ];

    public function setUp(): void
    {
        $this->db = TestDb::fresh();
        $this->clock = new FixedClock();
        $this->newSession();
        foreach (['owner' => null, 'warehouse' => null, 'brand_manager' => 3, 'support' => null] as $role => $brand) {
            $id = $this->db->insert('INSERT INTO users (email, name, password_hash, created_at) VALUES (?, ?, ?, ?)', ["{$role}@example.test", ucfirst($role), password_hash('correct horse battery', AuthController::algo()), $this->clock->now()]);
            $this->db->run('INSERT INTO user_roles (user_id, role, brand_id) VALUES (?, ?, ?)', [$id, $role, $brand]);
        }
        // One Milan Icons tee sold on Milan Icons and Schermo (shared), one unit left.
        $s = $this->app->services;
        $ids = $s->catalog()->createProduct(1, 'MI-TEE-001', 'Black tee', 'T-shirts', ['M'], 'test', 'apparel_size');
        $this->itemId = $ids['MI-TEE-001-M'];
        $s->ledger()->adjust($this->itemId, Adjustment::Received, 1, 'test');
        foreach ([1 => 100, 3 => 300] as $store => $wc) {
            $s->catalog()->mapListing($s->catalog()->recordListing($store, $wc, 0, 'MI-TEE-001-M', 'Tee'), $this->itemId, 'test', confirmShared: true);
        }
    }

    private int $itemId;

    private function newSession(): void
    {
        $this->session = new ArraySession();
        $this->app = new App($this->db, $this->clock, $this->session, $this->env);
    }

    private function get(string $path, array $query = []): Response
    {
        return $this->app->handle(new Request('GET', $path, $query));
    }

    private function post(string $path, array $form = [], bool $withCsrf = true): Response
    {
        if ($withCsrf) {
            $form['_csrf'] = $this->app->csrf();
        }
        return $this->app->handle(new Request('POST', $path, [], $form, ['referer' => 'http://hub.test' . $path]));
    }

    private function login(string $role): void
    {
        $this->newSession();
        $r = $this->post('/login', ['email' => "{$role}@example.test", 'password' => 'correct horse battery']);
        $this->assertSame(303, $r->status, "login as {$role}");
    }

    public function testSignedOutVisitorsAreSentToSignIn(): void
    {
        $r = $this->get('/inventory');
        $this->assertSame(303, $r->status);
        $this->assertSame('/login?next=%2Finventory', $r->headers['Location']);
        $this->assertStringContains("default-src 'self'", $r->headers['Content-Security-Policy']);
    }

    public function testWrongPasswordLocksOutAfterRepeatedAttemptsWithoutRevealingAccounts(): void
    {
        $unknown = $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'x']);
        $wrong = $this->post('/login', ['email' => 'owner@example.test', 'password' => 'x']);
        $this->assertSame(401, $unknown->status);
        $this->assertStringContains('The email or password is not correct.', $unknown->body);
        $this->assertStringContains('The email or password is not correct.', $wrong->body);
        for ($i = 0; $i < 5; $i++) {
            $r = $this->post('/login', ['email' => 'owner@example.test', 'password' => 'x']);
        }
        $this->assertStringContains('Too many sign-in attempts', $r->body);
        $this->clock->advance(901);
        $this->login('owner');
        for ($i = 0; $i < 8; $i++) {
            $this->login('owner'); // successful sign-ins never count towards the limit
        }
        $this->assertTrue($this->session->regenerated >= 1, 'session id regenerated at sign-in');
    }

    public function testFormsWithoutTheCsrfTokenAreRejected(): void
    {
        $this->login('owner');
        $r = $this->post('/inventory/' . $this->itemId . '/adjust', ['type' => 'received', 'quantity' => '5'], withCsrf: false);
        $this->assertSame(419, $r->status);
        $this->assertSame(1, $this->app->services->ledger()->level($this->itemId)->onHand, 'nothing changed');
    }

    public function testStockAdjustmentThroughTheDashboardIsRecordedAndUndoable(): void
    {
        $this->login('warehouse');
        $r = $this->post('/inventory/' . $this->itemId . '/adjust', ['type' => 'received', 'quantity' => '12', 'reason' => 'Delivery', 'return' => 'list']);
        $this->assertSame('/inventory', $r->headers['Location']);
        $this->assertSame(13, $this->app->services->ledger()->level($this->itemId)->onHand);
        $flash = $this->session->data['flash'];
        $this->assertStringContains('On hand 1 → 13', $flash['message']);
        $this->post($flash['undo']['action']);
        $this->assertSame(1, $this->app->services->ledger()->level($this->itemId)->onHand);
    }

    public function testRestrictedActionsAreRefusedOnTheServer(): void
    {
        $this->login('warehouse');
        $this->assertSame(403, $this->post('/inventory/' . $this->itemId . '/adjust', ['type' => 'lost', 'quantity' => '1', 'reason' => 'x'])->status, 'warehouse cannot record losses');
        $this->assertSame(403, $this->get('/team')->status);
        $this->assertSame(403, $this->post('/settings/warehouse', ['line1' => 'x', 'postcode' => '110001'])->status);
        $this->login('brand_manager'); // Schermo only
        $this->assertSame(200, $this->get('/inventory')->status);
        $this->assertSame(403, $this->post('/inventory/' . $this->itemId . '/adjust', ['type' => 'received', 'quantity' => '1'])->status);
        $this->assertSame(1, $this->app->services->ledger()->level($this->itemId)->onHand);
    }

    public function testBrandManagerSeesOnlyTheirBrandAndNoCustomerDetails(): void
    {
        $this->reserveFromStore('milan-icons', 'hub-secret-mi', 5001);
        $this->ingest(1, 5001, 'processing');
        $this->login('brand_manager');
        $orders = $this->get('/orders');
        $this->assertFalse(str_contains($orders->body, '#5001'), 'Milan Icons order hidden from a Schermo manager');
        $id = (int) $this->db->value('SELECT id FROM orders WHERE wc_order_id = 5001');
        $this->assertSame(403, $this->get("/orders/{$id}")->status);
        $this->login('support');
        $page = $this->get("/orders/{$id}")->body;
        $this->assertStringContains('buyer@example.test', $page);
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM audit_log WHERE action = 'order.view_customer'"), 'viewing customer details is audited');
    }

    public function testCheckoutReservationApiPreventsSellingTheLastUnitTwice(): void
    {
        $first = $this->reserveFromStore('milan-icons', 'hub-secret-mi', 7001);
        $this->assertSame(201, $first->status);
        $replay = $this->reserveFromStore('milan-icons', 'hub-secret-mi', 7001);
        $this->assertSame(200, $replay->status, 'retried checkout gets the same hold');
        $second = $this->reserveFromStore('schermo', 'hub-secret-schermo', 9001);
        $this->assertSame(409, $second->status);
        $this->assertSame('insufficient_stock', json_decode($second->body, true)['error']);
        $this->assertSame(1, $this->app->services->ledger()->level($this->itemId)->reserved);
    }

    public function testApiRejectsBadSignaturesAndUnlinkedProducts(): void
    {
        $body = json_encode(['order_id' => 1, 'lines' => [['product_id' => 100, 'quantity' => 1]]]);
        $bad = $this->app->handle(new Request('POST', '/api/v1/reservations', [], [], ['x-hub-store' => 'milan-icons', 'x-hub-timestamp' => (string) $this->clock->now()->getTimestamp(), 'x-hub-signature' => str_repeat('0', 64)], $body));
        $this->assertSame(401, $bad->status);
        $noSecret = $this->signed('significant-sports', 'whatever', '/api/v1/reservations', $body);
        $this->assertSame(401, $noSecret->status, 'a store without a configured secret is refused');
        $unlinked = $this->signed('schermo', 'hub-secret-schermo', '/api/v1/reservations', json_encode(['order_id' => 2, 'lines' => [['product_id' => 999, 'quantity' => 1, 'sku' => 'SCH-NEW-1']]]));
        $this->assertSame(409, $unlinked->status);
        $this->assertSame('not_linked', json_decode($unlinked->body, true)['error']);
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM listings WHERE wc_product_id = 999 AND status = 'unmapped'"), 'unknown product queued for linking');
    }

    public function testWooWebhookIsVerifiedAndDuplicatesAreIgnored(): void
    {
        $this->reserveFromStore('schermo', 'hub-secret-schermo', 9100);
        $payload = json_encode($this->order(9100, 300, 'processing'));
        $sig = base64_encode(hash_hmac('sha256', $payload, 'wh-secret-schermo', true));
        $headers = ['x-wc-webhook-signature' => $sig, 'x-wc-webhook-topic' => 'order.updated', 'x-wc-webhook-delivery-id' => 'd-1'];
        $this->assertSame(401, $this->app->handle(new Request('POST', '/api/v1/webhooks/woocommerce/3', [], [], ['x-wc-webhook-signature' => 'bad'] + $headers, $payload))->status);
        $ok = $this->app->handle(new Request('POST', '/api/v1/webhooks/woocommerce/3', [], [], $headers, $payload));
        $this->assertSame('applied', json_decode($ok->body, true)['result']);
        $dup = $this->app->handle(new Request('POST', '/api/v1/webhooks/woocommerce/3', [], [], $headers, $payload));
        $this->assertSame('duplicate', json_decode($dup->body, true)['result']);
        $this->assertSame('confirmed', $this->db->value("SELECT state FROM reservations WHERE order_ref = '9100'"));
        $this->assertSame('connected', $this->db->value('SELECT status FROM stores WHERE id = 3'));
    }

    public function testOrderWorkflowThroughTheDashboard(): void
    {
        $this->reserveFromStore('milan-icons', 'hub-secret-mi', 5100);
        $this->ingest(1, 5100, 'processing');
        $id = (int) $this->db->value('SELECT id FROM orders WHERE wc_order_id = 5100');
        $this->login('warehouse');
        $this->assertSame(303, $this->post("/orders/{$id}/action", ['action' => 'mark_packed'])->status);
        $this->assertStringContains('not available', $this->session->data['flash']['message'], 'skipping a step is refused');
        foreach (['start_processing', 'mark_picked', 'mark_packed'] as $a) {
            $this->post("/orders/{$id}/action", ['action' => $a]);
        }
        $this->post("/orders/{$id}/shipments", ['carrier' => 'DHL Express', 'service' => 'Express Worldwide', 'tracking' => 'ABC123']);
        $ship = (int) $this->db->value('SELECT id FROM shipments WHERE order_id = ?', [$id]);
        $this->post("/shipments/{$ship}/dispatch");
        $this->assertSame('shipped', $this->db->value('SELECT fulfilment_status FROM orders WHERE id = ?', [$id]));
        $this->assertSame(0, $this->app->services->ledger()->level($this->itemId)->onHand);
        $page = $this->get("/orders/{$id}")->body;
        $this->assertStringContains('data-stage="3"', $page, 'journey shows Dispatched, nothing beyond');
        $this->post("/orders/{$id}/action", ['action' => 'request_cancel', 'reason' => 'x']);
        $this->assertStringContains('not available', $this->session->data['flash']['message'], 'a dispatched order cannot be cancelled here');
        $this->assertSame(0, (int) $this->db->value("SELECT COUNT(*) FROM outbox WHERE topic = 'store.order_action'"));
    }

    public function testInventoryCsvImportPreviewsBeforeChangingStock(): void
    {
        $this->login('owner');
        $file = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($file, "sku,counted_quantity\nMI-TEE-001-M,9\nNOPE-1,3\n");
        $preview = $this->app->handle(new Request('POST', '/inventory/import', [], ['_csrf' => $this->app->csrf()], [], '', '127.0.0.1', ['file' => ['name' => 'count.csv', 'tmp_name' => $file, 'error' => UPLOAD_ERR_OK, 'size' => filesize($file)]]));
        $this->assertSame(200, $preview->status);
        $this->assertStringContains('No product has this SKU', $preview->body);
        $this->assertSame(1, $this->app->services->ledger()->level($this->itemId)->onHand, 'preview writes nothing');
        $hash = $this->session->data['import']['hash'];
        $this->post('/inventory/import/confirm', ['hash' => $hash]);
        $this->assertStringContains('1 rows have problems', $this->session->data['flash']['message']);
        $this->post('/inventory/import/confirm', ['hash' => $hash, 'skip_invalid' => '1']);
        $this->assertSame(9, $this->app->services->ledger()->level($this->itemId)->onHand);
        unlink($file);
    }

    public function testEveryPageRendersForTheOwner(): void
    {
        $this->reserveFromStore('milan-icons', 'hub-secret-mi', 5200);
        $this->ingest(1, 5200, 'processing');
        $this->login('owner');
        $order = (int) $this->db->value('SELECT id FROM orders LIMIT 1');
        $product = (int) $this->db->value('SELECT id FROM products LIMIT 1');
        foreach (['/', '/?range=7d', '/orders', "/orders/{$order}", '/orders/picklist', '/inventory', "/inventory/{$this->itemId}", "/inventory/{$this->itemId}/adjust", '/inventory/import', '/inventory/incoming', '/products', '/products/new', "/products/{$product}", '/shipments', '/returns', '/transfers', '/reports', '/team', '/settings', '/health', '/help', '/search'] as $path) {
            [$p, $q] = array_pad(explode('?', $path, 2), 2, '');
            parse_str($q, $query);
            $r = $this->get($p, $query);
            $this->assertSame(200, $r->status, $path);
            $this->assertFalse(str_contains($r->body, 'Warning:') || str_contains($r->body, 'Notice:'), "{$path} has no PHP warnings");
        }
        $panel = $this->get("/inventory/{$this->itemId}/adjust", ['panel' => '1']);
        $this->assertFalse(str_contains($panel->body, '<html'), 'side-panel fragment has no page shell');
    }

    // ── helpers ──

    private function signed(string $store, string $secret, string $path, string $body): Response
    {
        $ts = $this->clock->now()->getTimestamp();
        return $this->app->handle(new Request('POST', $path, [], [], ['x-hub-store' => $store, 'x-hub-timestamp' => (string) $ts, 'x-hub-signature' => Signature::sign($secret, $ts, 'POST', $path, $body)], $body));
    }

    private function reserveFromStore(string $store, string $secret, int $orderId): Response
    {
        $wc = $store === 'schermo' ? 300 : 100;
        return $this->signed($store, $secret, '/api/v1/reservations', json_encode(['order_id' => $orderId, 'lines' => [['product_id' => $wc, 'variation_id' => 0, 'quantity' => 1]]]));
    }

    private function order(int $id, int $wcProduct, string $status): array
    {
        return ['id' => $id, 'number' => (string) $id, 'status' => $status, 'currency' => 'INR', 'date_created_gmt' => '2026-11-01T09:00:00', 'date_modified_gmt' => '2026-11-01T09:05:00',
            'total' => '1499.00', 'transaction_id' => 'pay_1', 'payment_method' => 'razorpay',
            'billing' => ['first_name' => 'Test', 'last_name' => 'Buyer', 'email' => 'buyer@example.test', 'country' => 'GB'], 'shipping' => ['first_name' => 'Test', 'last_name' => 'Buyer', 'country' => 'GB'],
            'line_items' => [['id' => 1, 'product_id' => $wcProduct, 'variation_id' => 0, 'sku' => 'MI-TEE-001-M', 'name' => 'Black tee - M', 'quantity' => 1, 'total' => '1499.00']]];
    }

    private function ingest(int $store, int $orderId, string $status): void
    {
        $this->app->services->sync()->ingest($store, "t-{$orderId}-{$status}", $this->order($orderId, $store === 3 ? 300 : 100, $status));
    }
}
