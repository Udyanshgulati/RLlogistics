<?php

declare(strict_types=1);

namespace Commerce\Hub\Controllers;

use Commerce\Core\Integration\Signature;
use Commerce\Core\Inventory\IdempotencyConflict;
use Commerce\Core\Inventory\InsufficientStock;
use Commerce\Hub\Http\HttpError;
use Commerce\Hub\Http\Request;
use Commerce\Hub\Http\Response;
use Commerce\Hub\RateLimiter;

/**
 * Endpoints for the storefront connectors. No session or cookie: every call is authenticated with the
 * store's shared secret (see Signature), and refused when the store has no secret configured.
 */
final class ApiController extends Controller
{
    /** Unpaid checkout hold; WooCommerce's own "hold stock" should be shorter so it cancels first. */
    private const HOLD_SECONDS = 2700;
    private const HARD_HOLD_SECONDS = 172800;

    /**
     * POST /api/v1/reservations, called synchronously during checkout, before payment.
     * Body: {"order_id": 123, "lines": [{"product_id": 10, "variation_id": 11, "quantity": 2}]}
     * 201 held · 409 insufficient_stock / not_linked · 401 bad signature. The connector must refuse the
     * checkout on anything but 200/201 (fail closed: no hub answer, no sale).
     */
    public function reserve(Request $req): Response
    {
        $store = $this->authenticate($req);
        $body = json_decode($req->body, true);
        if (!is_array($body) || !isset($body['order_id'], $body['lines']) || !is_array($body['lines']) || $body['lines'] === []) {
            throw new HttpError(422, 'order_id and lines are required.');
        }
        $orderId = (int) $body['order_id'];
        $items = [];
        $byItem = [];
        foreach ($body['lines'] as $l) {
            $pid = (int) ($l['product_id'] ?? 0);
            $vid = (int) ($l['variation_id'] ?? 0);
            $q = (int) ($l['quantity'] ?? 0);
            if ($pid <= 0 || $q <= 0) {
                throw new HttpError(422, 'Each line needs product_id and a positive quantity.');
            }
            $item = $this->db->value("SELECT inventory_item_id FROM listings WHERE store_id = ? AND wc_product_id = ? AND wc_variation_id = ? AND status = 'mapped'", [$store['id'], $pid, $vid]);
            if ($item === null) {
                $this->app->services->catalog()->recordListing((int) $store['id'], $pid, $vid, $l['sku'] ?? null, $l['name'] ?? null);
                return Response::json(['error' => 'not_linked', 'product_id' => $pid, 'variation_id' => $vid, 'message' => 'This item is temporarily unavailable.'], 409);
            }
            $items[(int) $item] = ($items[(int) $item] ?? 0) + $q;
            $byItem[(int) $item] = ['product_id' => $pid, 'variation_id' => $vid];
        }
        try {
            $r = $this->app->services->ledger()->reserve((int) $store['id'], (string) $orderId, $items, "store:{$store['id']}:order:{$orderId}", self::HOLD_SECONDS, self::HARD_HOLD_SECONDS);
        } catch (InsufficientStock $e) {
            $short = [];
            foreach ($e->shortfalls as $item => $s) {
                $short[] = $byItem[$item] + ['available' => $s['available']];
            }
            return Response::json(['error' => 'insufficient_stock', 'items' => $short, 'message' => 'Some items in your basket have just sold out.'], 409);
        } catch (IdempotencyConflict) {
            // The basket changed after a hold was placed for this order: let the connector start a new order.
            return Response::json(['error' => 'order_changed', 'message' => 'Your basket changed. Please review it and try again.'], 409);
        }
        if (!$r->ok) {
            return Response::json(['error' => 'order_closed', 'message' => 'This order can no longer be paid. Please place a new order.'], 409);
        }
        $res = $this->app->services->ledger()->reservation($r->reservationId);
        return Response::json(['reservation_id' => $r->reservationId, 'state' => $r->state, 'replayed' => $r->replayed, 'expires_at' => $res['expires_at']], $r->replayed ? 200 : 201);
    }

    /** POST /api/v1/webhooks/woocommerce/{store id}: WooCommerce "Order created/updated" webhooks. */
    public function wooWebhook(Request $req, array $p): Response
    {
        $store = $this->db->one('SELECT * FROM stores WHERE id = ?', [(int) $p['id']]) ?? throw new HttpError(404, 'Unknown store.');
        $secret = $this->secret($store['code'], 'WEBHOOK_SECRET');
        if (!Signature::verifyWooWebhook($secret, $req->header('x-wc-webhook-signature'), $req->body)) {
            throw new HttpError(401, 'Invalid signature.');
        }
        $this->limit($store);
        $this->db->run("UPDATE stores SET last_seen_at = ?, status = IF(status = 'paused', status, 'connected') WHERE id = ?", [$this->app->clock->now(), $store['id']]);
        $topic = (string) $req->header('x-wc-webhook-topic');
        if (!in_array($topic, ['order.created', 'order.updated', 'order.restored'], true)) {
            return Response::json(['ok' => true, 'ignored' => $topic ?: 'ping']);
        }
        $order = json_decode($req->body, true);
        if (!is_array($order) || !isset($order['id'], $order['status'])) {
            throw new HttpError(422, 'Not an order payload.');
        }
        $delivery = $req->header('x-wc-webhook-delivery-id') ?: hash('sha256', $req->body);
        $result = $this->app->services->sync()->ingest((int) $store['id'], 'wc:' . $delivery, $order);
        return Response::json(['ok' => true, 'result' => $result]);
    }

    /** GET /api/v1/markets: the shared market configuration, for storefronts to cache. */
    public function markets(Request $req): Response
    {
        $this->authenticate($req);
        return Response::json($this->app->services->markets()->data);
    }

    /** GET /api/v1/health: liveness for uptime monitoring. Reveals nothing beyond up/down. */
    public function health(Request $req): Response
    {
        try {
            $this->db->value('SELECT 1');
            return Response::json(['ok' => true]);
        } catch (\Throwable) {
            return Response::json(['ok' => false], 503);
        }
    }

    private function authenticate(Request $req): array
    {
        $store = $this->db->one('SELECT * FROM stores WHERE code = ?', [(string) $req->header('x-hub-store')]);
        $secret = $store === null ? '' : $this->secret($store['code'], 'HUB_SECRET');
        if ($store === null || !Signature::verify($secret, $req->header('x-hub-timestamp'), $req->header('x-hub-signature'), $req->method, $req->path, $req->body, $this->app->clock->now()->getTimestamp())) {
            throw new HttpError(401, 'Invalid signature.');
        }
        if ($store['status'] === 'paused') {
            throw new HttpError(503, 'This storefront is paused in the operations hub.');
        }
        $this->limit($store);
        $this->db->run('UPDATE stores SET last_seen_at = ? WHERE id = ?', [$this->app->clock->now(), $store['id']]);
        return $store;
    }

    private function limit(array $store): void
    {
        if (!(new RateLimiter($this->db, $this->app->clock))->hit('api:store:' . $store['id'], 1200, 60)) {
            throw new HttpError(429, 'Too many requests.');
        }
    }

    private function secret(string $storeCode, string $suffix): string
    {
        return (string) ($this->app->env['STORE_' . strtoupper(str_replace('-', '_', $storeCode)) . '_' . $suffix] ?? '');
    }
}
