<?php

declare(strict_types=1);

namespace Commerce\Hub\Controllers;

use Commerce\Hub\Http\HttpError;
use Commerce\Hub\Http\Request;
use Commerce\Hub\Http\Response;

/**
 * The home screen answers: what needs my attention, how many orders came in, how much stock we have,
 * what is waiting to be dispatched, and what is running low. Every number is a live query.
 */
final class OverviewController extends Controller
{
    public const SLOW_HOURS = 24;

    public function index(Request $req): Response
    {
        $this->app->user->require('overview.view', anyBrand: true);
        [$from, $to, $range] = $this->range($req);
        $orderScope = $this->app->user->can('orders.view', anyBrand: true) ? $this->app->scope('orders.view') : [];
        $invScope = $this->app->user->can('inventory.view', anyBrand: true) ? $this->app->scope('inventory.view') : [];
        $os = $this->sqlScope($orderScope, 's.brand_id');
        $base = "FROM orders o JOIN stores s ON s.id = o.store_id WHERE {$os}";

        $kpi = [
            'new' => (int) $this->db->value("SELECT COUNT(*) {$base} AND o.placed_at >= ? AND o.placed_at < ? AND o.payment_status <> 'failed'", [$from, $to]),
            'to_process' => (int) $this->db->value("SELECT COUNT(*) {$base} AND o.payment_status = 'paid' AND o.fulfilment_status IN ('unfulfilled','processing','picked')"),
            'ready' => (int) $this->db->value("SELECT COUNT(*) {$base} AND o.payment_status = 'paid' AND o.fulfilment_status = 'packed'"),
            'in_transit' => (int) $this->db->value("SELECT COUNT(*) FROM shipments sh JOIN orders o ON o.id = sh.order_id JOIN stores s ON s.id = o.store_id WHERE {$os} AND sh.status IN ('dispatched','in_transit','exception')"),
            'delivered' => (int) $this->db->value("SELECT COUNT(*) FROM shipments sh JOIN orders o ON o.id = sh.order_id JOIN stores s ON s.id = o.store_id WHERE {$os} AND sh.status = 'delivered' AND sh.delivered_at >= ? AND sh.delivered_at < ?", [$from, $to]),
        ];
        $stock = $invScope === [] ? ['skus' => 0, 'on_hand' => 0, 'available' => 0, 'out_of_stock' => 0, 'low_stock' => 0] : $this->app->services->inventory()->totals(null, $invScope);

        return $this->app->page($req, 'overview', [
            'title' => 'Overview',
            'nav' => 'overview',
            'range' => $range,
            'from' => $from,
            'to' => $to,
            'kpi' => $kpi,
            'stock' => $stock,
            'showOrders' => $orderScope !== [],
            'showStock' => $invScope !== [],
            'attention' => $this->attention($os, $kpi, $stock),
            'checklist' => $this->checklist(),
            'activity' => $this->activity($os),
        ]);
    }

    /** Each item: [count, message, link, action label, tone]. Only non-zero items are shown. */
    private function attention(string $os, array $kpi, array $stock): array
    {
        $u = $this->app->user;
        $items = [];
        if ($u->can('orders.view', anyBrand: true)) {
            $slow = (int) $this->db->value(
                "SELECT COUNT(*) FROM orders o JOIN stores s ON s.id = o.store_id WHERE {$os} AND o.payment_status = 'paid' AND o.fulfilment_status = 'unfulfilled' AND o.placed_at < ?",
                [$this->app->clock->now()->modify('-' . self::SLOW_HOURS . ' hours')],
            );
            $problems = (int) $this->db->value("SELECT COUNT(*) FROM orders o JOIN stores s ON s.id = o.store_id WHERE {$os} AND o.attention IS NOT NULL");
            $items[] = [$problems, 'orders have a problem that blocks fulfilment', '/orders?attention=1', 'Review orders', 'bad'];
            $items[] = [$slow, 'paid orders have waited more than ' . self::SLOW_HOURS . ' hours to be processed', '/orders?fulfilment=unfulfilled&payment=paid', 'View orders', 'warn'];
            $items[] = [$kpi['ready'], 'orders are packed and waiting for dispatch', '/orders?fulfilment=packed', 'Dispatch orders', 'info'];
        }
        if ($u->can('inventory.view', anyBrand: true)) {
            $items[] = [$stock['out_of_stock'], 'products are out of stock', '/inventory?status=out', 'Restock products', 'bad'];
            $items[] = [$stock['low_stock'], 'products are below their reorder level', '/inventory?status=low', 'Restock products', 'warn'];
        }
        if ($u->can('mapping.manage', anyBrand: true)) {
            $items[] = [(int) $this->db->value("SELECT COUNT(*) FROM listings WHERE status = 'unmapped'"), 'storefront products are not linked to stock yet', '/products?unlinked=1', 'Link products', 'warn'];
        }
        if ($u->can('integrations.view')) {
            $failedIn = (int) $this->db->value("SELECT COUNT(*) FROM inbox_events WHERE status = 'failed'");
            $dead = $this->db->all("SELECT topic, COUNT(*) n FROM outbox WHERE status = 'dead' GROUP BY topic");
            $byGroup = ['stock.push' => 'inventory updates to storefronts failed', 'notify.customer' => 'customer notifications failed', 'alert.stock' => 'stock alerts could not be sent'];
            $items[] = [$failedIn, 'order updates from storefronts could not be processed', '/health', 'Open integration health', 'bad'];
            foreach ($dead as $d) {
                $items[] = [(int) $d['n'], $byGroup[$d['topic']] ?? (str_starts_with($d['topic'], 'store.') ? 'requests to storefronts failed (cancellations, refunds, tracking)' : "background jobs failed ({$d['topic']})"), '/health', 'Open integration health', 'bad'];
            }
            $items[] = [(int) $this->db->value("SELECT COUNT(*) FROM shipments WHERE status = 'exception'"), 'shipments have a delivery problem reported by the carrier', '/shipments?status=exception', 'View shipments', 'bad'];
        }
        return array_values(array_filter($items, fn ($i) => $i[0] > 0));
    }

    /** Set-up steps, each checked against real data. */
    private function checklist(): array
    {
        $wh = $this->db->one('SELECT address_json FROM warehouses WHERE id = 1');
        return [
            ['Add the Delhi warehouse address and pickup details', $wh !== null && $wh['address_json'] !== null, '/settings'],
            ['Confirm storefront addresses for all three brands', (int) $this->db->value('SELECT COUNT(*) FROM stores WHERE base_url IS NULL') === 0, '/settings'],
            ['Connect the storefronts', (int) $this->db->value("SELECT COUNT(*) FROM stores WHERE status <> 'connected'") === 0, '/settings'],
            ['Create products and sizes', (int) $this->db->value('SELECT COUNT(*) FROM products') > 0, '/products/new'],
            ['Record opening stock', (int) $this->db->value("SELECT COUNT(*) FROM stock_movements WHERE type LIKE 'adjust.%' OR type IN ('stocktake','purchase.receive')") > 0, '/inventory'],
            ['Link every storefront product to stock', (int) $this->db->value('SELECT COUNT(*) FROM listings') > 0 && (int) $this->db->value("SELECT COUNT(*) FROM listings WHERE status = 'unmapped'") === 0, '/products?unlinked=1'],
            ['Invite the team', (int) $this->db->value('SELECT COUNT(*) FROM users') > 1, '/team'],
        ];
    }

    private function activity(string $os): array
    {
        $rows = [];
        if ($this->app->user->can('orders.view', anyBrand: true)) {
            $rows = $this->db->all("SELECT e.created_at, e.message, o.id AS order_id, o.order_number, s.brand_id FROM order_events e JOIN orders o ON o.id = e.order_id JOIN stores s ON s.id = o.store_id WHERE {$os} ORDER BY e.id DESC LIMIT 8");
        }
        if ($this->app->user->can('inventory.view', anyBrand: true)) {
            $moves = $this->db->all("SELECT m.created_at, m.type, m.d_on_hand, m.d_damaged, m.actor, i.sku, i.id AS item_id FROM stock_movements m JOIN inventory_items i ON i.id = m.inventory_item_id WHERE m.type LIKE 'adjust.%' OR m.type IN ('stocktake','purchase.receive','return.restock','return.damaged') ORDER BY m.id DESC LIMIT 8");
            foreach ($moves as $m) {
                $rows[] = ['created_at' => $m['created_at'], 'message' => self::movementLabel($m['type']) . " · {$m['sku']} (" . sprintf('%+d', (int) $m['d_on_hand'] ?: (int) $m['d_damaged']) . ')', 'item_id' => $m['item_id']];
            }
        }
        usort($rows, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));
        return array_slice($rows, 0, 10);
    }

    public static function movementLabel(string $type): string
    {
        return [
            'opening' => 'Opening stock', 'adjust.received' => 'Stock received', 'adjust.damaged' => 'Marked damaged', 'adjust.lost' => 'Lost stock',
            'adjust.correction' => 'Stock correction', 'adjust.returned_to_inventory' => 'Returned to inventory', 'adjust.other' => 'Other adjustment', 'adjust.undo' => 'Adjustment undone',
            'stocktake' => 'Stocktake', 'damaged.write_off' => 'Damaged stock written off', 'purchase.receive' => 'Purchase order received',
            'return.receive' => 'Return received (awaiting inspection)', 'return.restock' => 'Return approved for resale', 'return.damaged' => 'Return found damaged',
            'reserve' => 'Reserved for an order', 'reserve.retry' => 'Reserved again (payment retried)', 'reserve.late_payment' => 'Reserved again (late payment)',
            'release' => 'Reservation released', 'expire' => 'Unpaid hold expired', 'dispatch' => 'Dispatched',
        ][$type] ?? $type;
    }

    public function search(Request $req): Response
    {
        $q = $req->q('q');
        $orders = $items = [];
        if (mb_strlen($q) >= 2) {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            if ($this->app->user->can('orders.view', anyBrand: true)) {
                $os = $this->sqlScope($this->app->scope('orders.view'), 's.brand_id');
                $pii = $this->app->user->can('orders.view_customer', anyBrand: true) ? ' OR o.customer_email = ? OR o.customer_name LIKE ?' : '';
                $params = $pii ? [$q, $like, strtolower($q), $like] : [$q, $like];
                $orders = $this->db->all("SELECT o.*, s.brand_id FROM orders o JOIN stores s ON s.id = o.store_id WHERE {$os} AND (o.order_number = ? OR o.order_number LIKE ?{$pii}) ORDER BY o.placed_at DESC LIMIT 20", $params);
            }
            if ($this->app->user->can('inventory.view', anyBrand: true)) {
                $items = $this->app->services->inventory()->list(['q' => $q], $this->app->scope('inventory.view'), 20)['rows'];
            }
            if (count($orders) === 1 && $items === []) {
                return Response::redirect('/orders/' . $orders[0]['id']);
            }
            if (count($items) === 1 && $orders === []) {
                return Response::redirect('/inventory/' . $items[0]['id']);
            }
        }
        return $this->app->page($req, 'search', ['title' => 'Search', 'q' => $q, 'orders' => $orders, 'items' => $items]);
    }

    public function help(Request $req): Response
    {
        return $this->app->page($req, 'help', ['title' => 'Help', 'nav' => 'help']);
    }

    /** Top-bar brand filter. */
    public function setContext(Request $req): Response
    {
        $brand = $req->int('brand', null, true);
        if ($brand !== null && !isset($this->app->services->brands()[$brand])) {
            throw new HttpError(400, 'Unknown brand.');
        }
        $brand === null ? $this->app->session->remove('brand') : $this->app->session->set('brand', $brand);
        return Response::redirect($this->app->back($req));
    }

    public function saveView(Request $req): Response
    {
        $page = $req->p('page');
        $name = mb_substr($req->p('name'), 0, 60);
        if (!in_array($page, ['orders', 'inventory'], true) || $name === '') {
            throw new HttpError(400, 'Give the view a name.');
        }
        parse_str($req->p('query'), $query);
        unset($query['page']);
        $this->db->run(
            'INSERT INTO saved_views (user_id, page, name, query, created_at) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE query = VALUES(query)',
            [$this->app->user->userId, $page, $name, http_build_query($query), $this->app->clock->now()],
        );
        $this->app->flash('success', "Saved view \"{$name}\".");
        return Response::redirect('/' . $page . '?' . http_build_query($query));
    }

    /** @return array{\DateTimeImmutable, \DateTimeImmutable, string} UTC bounds for the chosen range, in the display timezone's days */
    private function range(Request $req): array
    {
        $tz = new \DateTimeZone($this->app->env['DISPLAY_TZ'] ?? 'Asia/Kolkata');
        $utc = new \DateTimeZone('UTC');
        $today = $this->app->clock->now()->setTimezone($tz)->setTime(0, 0);
        $range = $req->q('range', 'today');
        [$from, $to] = match ($range) {
            '7d' => [$today->modify('-6 days'), $today->modify('+1 day')],
            '30d' => [$today->modify('-29 days'), $today->modify('+1 day')],
            'custom' => [
                \DateTimeImmutable::createFromFormat('!Y-m-d', $req->q('from'), $tz) ?: $today,
                (\DateTimeImmutable::createFromFormat('!Y-m-d', $req->q('to'), $tz) ?: $today)->modify('+1 day'),
            ],
            default => [$today, $today->modify('+1 day')],
        };
        if (!in_array($range, ['today', '7d', '30d', 'custom'], true)) {
            $range = 'today';
        }
        return [$from->setTimezone($utc), $to->setTimezone($utc), $range];
    }
}
