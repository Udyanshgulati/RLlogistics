<?php

declare(strict_types=1);

namespace Commerce\Hub\Controllers;

use Commerce\Core\Orders\FulfilmentException;
use Commerce\Hub\Http\HttpError;
use Commerce\Hub\Http\Request;
use Commerce\Hub\Http\Response;

final class OrdersController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $req): Response
    {
        $scope = $this->app->scope('orders.view');
        [$where, $params] = $this->filters($req, $scope);
        $page = max(1, $req->int('page', 1));
        $total = (int) $this->db->value("SELECT COUNT(*) FROM orders o JOIN stores s ON s.id = o.store_id WHERE {$where}", $params);
        $orders = $this->db->all(
            "SELECT o.*, s.brand_id,
                    (SELECT SUM(qty) FROM order_lines WHERE order_id = o.id) AS units,
                    (SELECT GROUP_CONCAT(CONCAT(qty, ' × ', COALESCE(sku, name)) ORDER BY id SEPARATOR '\n') FROM order_lines WHERE order_id = o.id) AS items,
                    (SELECT tracking_number FROM shipments WHERE order_id = o.id ORDER BY id DESC LIMIT 1) AS tracking,
                    (SELECT CONCAT(carrier, ' ', service) FROM shipments WHERE order_id = o.id ORDER BY id DESC LIMIT 1) AS shipped_with
             FROM orders o JOIN stores s ON s.id = o.store_id WHERE {$where} ORDER BY o.placed_at DESC LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE),
            $params,
        );
        $fulfil = $this->app->services->fulfilment();
        foreach ($orders as &$o) {
            $o['next'] = $fulfil->nextActions((int) $o['id'])[0] ?? null;
        }
        return $this->app->page($req, 'orders/index', [
            'title' => 'Orders', 'nav' => 'orders', 'orders' => $orders, 'total' => $total, 'page' => $page, 'perPage' => self::PER_PAGE,
            'countries' => array_column($this->db->all('SELECT DISTINCT ship_country FROM orders WHERE ship_country IS NOT NULL ORDER BY ship_country'), 'ship_country'),
            'services' => array_column($this->db->all('SELECT DISTINCT shipping_service_requested s FROM orders WHERE shipping_service_requested IS NOT NULL ORDER BY s'), 's'),
            'views' => $this->db->all("SELECT name, query FROM saved_views WHERE user_id = ? AND page = 'orders' ORDER BY name", [$this->app->user->userId]),
            'canPii' => $this->app->user->can('orders.view_customer', anyBrand: true),
        ]);
    }

    public function show(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        $brand = $this->brandOfOrder($id);
        $this->app->user->require('orders.view', $brand);
        $o = $this->db->one('SELECT o.*, s.brand_id, s.name AS store_name FROM orders o JOIN stores s ON s.id = o.store_id WHERE o.id = ?', [$id]);
        $lines = $this->db->all('SELECT ol.*, i.sku AS central_sku FROM order_lines ol LEFT JOIN inventory_items i ON i.id = ol.inventory_item_id WHERE ol.order_id = ? ORDER BY ol.id', [$id]);
        $shipments = $this->db->all('SELECT * FROM shipments WHERE order_id = ? ORDER BY id', [$id]);
        $tracking = [];
        foreach ($shipments as $s) {
            $tracking[$s['id']] = $this->db->all('SELECT * FROM tracking_events WHERE shipment_id = ? ORDER BY occurred_at DESC', [$s['id']]);
        }
        $pii = $this->app->user->can('orders.view_customer', $brand);
        if ($pii) {
            $this->app->services->audit()->log($this->actor(), 'order.view_customer', 'order', $id, [], $req->ip);
        }
        return $this->app->page($req, 'orders/show', [
            'title' => 'Order ' . $o['order_number'], 'nav' => 'orders', 'o' => $o, 'lines' => $lines, 'shipments' => $shipments, 'tracking' => $tracking,
            'events' => $this->db->all('SELECT * FROM order_events WHERE order_id = ? ORDER BY id', [$id]),
            'reservation' => $o['reservation_id'] ? $this->app->services->ledger()->reservation((int) $o['reservation_id']) : null,
            'returns' => $this->db->all('SELECT * FROM returns WHERE order_id = ? ORDER BY id', [$id]),
            'actions' => $this->app->services->fulfilment()->nextActions($id),
            'pii' => $pii, 'brand' => $brand,
        ]);
    }

    public function action(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        $brand = $this->brandOfOrder($id);
        $f = $this->app->services->fulfilment();
        $action = $req->p('action');
        if (!in_array($action, $f->nextActions($id), true) && $action !== 'recheck' && $action !== 'clear_exception') {
            throw new FulfilmentException('That step is not available for this order any more. The page has been refreshed.');
        }
        $message = match ($action) {
            'start_processing' => $this->fulfil($brand, fn () => $f->startProcessing($id, $this->actor()), 'Processing started. The order is on the pick list.'),
            'mark_picked' => $this->fulfil($brand, fn () => $f->markPicked($id, $this->actor()), 'Marked as picked.'),
            'mark_packed' => $this->fulfil($brand, fn () => $f->markPacked($id, $this->actor()), 'Marked as packed. Create the shipment next.'),
            'request_cancel' => $this->guarded('orders.request_cancel', $brand, fn () => $f->requestStoreAction($id, 'cancel', $this->actor(), $req->p('reason')), 'Cancellation sent to the storefront. Stock is released when the storefront confirms.'),
            'request_mark_paid' => $this->guarded('payments.mark_paid', $brand, fn () => $f->requestStoreAction($id, 'mark_paid', $this->actor(), $req->p('reason')), 'Payment note sent to the storefront. The order becomes Paid when the storefront confirms.'),
            'recheck' => $this->guarded('orders.fulfil', $brand, fn () => $this->app->services->sync()->recheck($id, $this->actor()), null),
            'clear_exception' => $this->guarded('orders.fulfil', $brand, function () use ($id, $req) {
                if (trim($req->p('reason')) === '') {
                    throw new FulfilmentException('Add a note saying how the delivery problem was resolved.');
                }
                $this->db->run("UPDATE orders SET attention = NULL WHERE id = ? AND attention = 'delivery_exception'", [$id]);
                $this->db->run('INSERT INTO order_events (order_id, type, message, actor, created_at) VALUES (?, ?, ?, ?, ?)', [$id, 'attention.resolved', 'Delivery problem resolved: ' . mb_substr($req->p('reason'), 0, 400), $this->actor(), $this->app->clock->now()]);
            }, 'Marked as resolved.'),
            default => throw new HttpError(400, 'Unknown action.'),
        };
        if ($action === 'recheck') {
            $left = $this->db->value('SELECT attention FROM orders WHERE id = ?', [$id]);
            $message = $left === null ? 'Resolved. Stock is reserved and the order can be processed.' : 'Still blocked: ' . (\Commerce\Hub\View::ATTENTION[$left] ?? $left) . '.';
            $this->app->flash($left === null ? 'success' : 'error', $message);
        } else {
            $this->app->flash('success', $message);
        }
        return Response::redirect("/orders/{$id}");
    }

    public function createShipment(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        $this->app->user->require('orders.fulfil', $this->brandOfOrder($id));
        $qty = null;
        if ($req->p('partial') === '1') {
            $qty = [];
            foreach ((array) ($req->post['qty'] ?? []) as $line => $q) {
                $qty[(int) $line] = is_string($q) && ctype_digit($q) ? (int) $q : 0;
            }
        }
        $this->app->services->fulfilment()->createShipment($id, $req->p('carrier'), $req->p('service'), $req->p('tracking') ?: null, null, $this->actor(), $qty);
        $this->app->flash('success', $req->p('tracking') ? 'Shipment created. Mark it as dispatched when the carrier collects it.' : 'Shipment created. Add the tracking number when you dispatch.');
        return Response::redirect("/orders/{$id}");
    }

    public function dispatch(Request $req, array $p): Response
    {
        $s = $this->db->one('SELECT * FROM shipments WHERE id = ?', [(int) $p['id']]) ?? throw new HttpError(404, 'Unknown shipment.');
        $this->app->user->require('orders.fulfil', $this->brandOfOrder((int) $s['order_id']));
        $this->app->services->fulfilment()->dispatch((int) $s['id'], $this->actor(), $req->p('tracking') ?: null);
        $this->app->flash('success', 'Dispatched. Stock has been deducted and the customer will be notified.');
        return Response::redirect('/orders/' . $s['order_id']);
    }

    public function delivered(Request $req, array $p): Response
    {
        $s = $this->db->one('SELECT * FROM shipments WHERE id = ?', [(int) $p['id']]) ?? throw new HttpError(404, 'Unknown shipment.');
        $this->app->user->require('orders.fulfil', $this->brandOfOrder((int) $s['order_id']));
        $this->app->services->fulfilment()->markDelivered((int) $s['id'], $this->actor());
        $this->app->flash('success', 'Marked as delivered (recorded as confirmed by staff, not by the carrier).');
        return Response::redirect('/orders/' . $s['order_id']);
    }

    public function openReturn(Request $req, array $p): Response
    {
        $id = (int) $p['id'];
        $this->app->user->require('returns.open', $this->brandOfOrder($id));
        $qty = [];
        foreach ((array) ($req->post['qty'] ?? []) as $line => $q) {
            if (is_string($q) && ctype_digit($q) && (int) $q > 0) {
                $qty[(int) $line] = (int) $q;
            }
        }
        $rid = $this->app->services->returns()->open($id, $qty, $req->p('reason'), $req->p('kind') === 'exchange' ? 'exchange' : 'return', $this->actor());
        $this->app->flash('success', 'Return opened. Record the items when they arrive at the warehouse.');
        return Response::redirect("/returns/{$rid}");
    }

    public function pickList(Request $req): Response
    {
        $this->app->user->require('orders.fulfil', anyBrand: true);
        $scope = $this->app->scope('orders.fulfil');
        $ids = array_filter(array_map('intval', explode(',', $req->q('ids'))));
        if ($ids === []) {
            $ids = array_map('intval', array_column($this->db->all(
                "SELECT o.id FROM orders o JOIN stores s ON s.id = o.store_id WHERE " . $this->sqlScope($scope, 's.brand_id') . " AND o.payment_status = 'paid' AND o.attention IS NULL AND o.fulfilment_status = 'processing' ORDER BY o.placed_at LIMIT 200",
            ), 'id'));
        }
        foreach ($ids as $id) {
            $this->app->user->require('orders.fulfil', $this->brandOfOrder($id));
        }
        return $this->app->page($req, 'orders/picklist', ['title' => 'Pick list', 'nav' => 'orders', 'rows' => $this->app->services->fulfilment()->pickList($ids), 'count' => count($ids)]);
    }

    public function shipments(Request $req): Response
    {
        $scope = $this->app->scope('orders.view');
        $where = [$this->sqlScope($scope, 's.brand_id')];
        $params = [];
        if (($st = $req->q('status')) !== '') {
            $where[] = 'sh.status = ?';
            $params[] = $st;
        }
        if (($c = $req->q('carrier')) !== '') {
            $where[] = 'sh.carrier = ?';
            $params[] = $c;
        }
        if (($cc = strtoupper($req->q('country'))) !== '') {
            $where[] = 'o.ship_country = ?';
            $params[] = $cc;
        }
        $rows = $this->db->all(
            'SELECT sh.*, o.order_number, o.ship_country, s.brand_id FROM shipments sh JOIN orders o ON o.id = sh.order_id JOIN stores s ON s.id = o.store_id WHERE ' . implode(' AND ', $where) . ' ORDER BY sh.id DESC LIMIT 200',
            $params,
        );
        return $this->app->page($req, 'shipments', ['title' => 'Shipments', 'nav' => 'shipments', 'rows' => $rows, 'carriers' => array_column($this->db->all('SELECT DISTINCT carrier FROM shipments ORDER BY carrier'), 'carrier')]);
    }

    private function filters(Request $req, ?array $scope): array
    {
        $where = [$this->sqlScope($scope, 's.brand_id')];
        $params = [];
        $map = ['payment' => 'o.payment_status', 'fulfilment' => 'o.fulfilment_status', 'service' => 'o.shipping_service_requested'];
        foreach ($map as $key => $col) {
            if (($v = $req->q($key)) !== '') {
                $where[] = "{$col} = ?";
                $params[] = $v;
            }
        }
        if (($c = strtoupper($req->q('country'))) !== '') {
            $where[] = 'o.ship_country = ?';
            $params[] = $c;
        }
        if ($req->q('attention') === '1') {
            $where[] = 'o.attention IS NOT NULL';
        }
        $tz = new \DateTimeZone($this->app->env['DISPLAY_TZ'] ?? 'Asia/Kolkata');
        foreach (['from' => '>=', 'to' => '<'] as $k => $op) {
            if (($d = \DateTimeImmutable::createFromFormat('!Y-m-d', $req->q($k), $tz)) !== false) {
                $where[] = "o.placed_at {$op} ?";
                $params[] = ($k === 'to' ? $d->modify('+1 day') : $d)->setTimezone(new \DateTimeZone('UTC'));
            }
        }
        if (($q = $req->q('q')) !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(o.order_number LIKE ? OR EXISTS (SELECT 1 FROM order_lines ol WHERE ol.order_id = o.id AND ol.sku LIKE ?))';
            array_push($params, $like, $like);
        }
        return [implode(' AND ', $where), $params];
    }

    private function fulfil(int $brand, callable $fn, string $message): string
    {
        return $this->guarded('orders.fulfil', $brand, $fn, $message);
    }

    private function guarded(string $permission, int $brand, callable $fn, ?string $message): ?string
    {
        $this->app->user->require($permission, $brand);
        $fn();
        return $message;
    }
}
