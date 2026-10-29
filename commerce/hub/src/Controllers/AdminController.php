<?php

declare(strict_types=1);

namespace Commerce\Hub\Controllers;

use Commerce\Core\Access\Roles;
use Commerce\Core\Inventory\InventoryCsv;
use Commerce\Core\Inventory\LedgerException;
use Commerce\Core\Inventory\Reconciler;
use Commerce\Hub\Http\HttpError;
use Commerce\Hub\Http\Request;
use Commerce\Hub\Http\Response;

/** Stock transfers, reports, team, settings and integration health. */
final class AdminController extends Controller
{
    public function transfers(Request $req): Response
    {
        $this->app->user->require('inventory.view', anyBrand: true);
        return $this->app->page($req, 'transfers', ['title' => 'Stock transfers', 'nav' => 'transfers', 'warehouses' => $this->db->all('SELECT * FROM warehouses ORDER BY id')]);
    }

    // ── Reports ─────────────────────────────────────────────────────────────

    public function reports(Request $req): Response
    {
        $data = $this->reportData($req);
        return $this->app->page($req, 'reports', ['title' => 'Reports', 'nav' => 'reports'] + $data);
    }

    public function reportExport(Request $req): Response
    {
        $data = $this->reportData($req);
        $which = $req->q('report');
        $rows = $data[$which] ?? throw new HttpError(404, 'Unknown report.');
        if ($rows === []) {
            throw new LedgerException('This report has no rows for the selected dates.');
        }
        $h = fopen('php://temp', 'w+');
        fputcsv($h, array_keys($rows[0]), escape: '');
        foreach ($rows as $r) {
            fputcsv($h, array_map(InventoryCsv::safeCell(...), $r), escape: '');
        }
        rewind($h);
        return Response::csv("{$which}-{$data['fromLabel']}-{$data['toLabel']}.csv", (string) stream_get_contents($h));
    }

    /**
     * Definitions (also shown on the page):
     *   Gross order value = order totals of paid, refunded and partly refunded orders, including shipping and tax.
     *   Net sales = gross order value − refunds. Taxes and shipping are listed separately, not deducted.
     * Amounts are grouped by currency and never added across currencies.
     */
    private function reportData(Request $req): array
    {
        $tz = new \DateTimeZone($this->app->env['DISPLAY_TZ'] ?? 'Asia/Kolkata');
        $utc = new \DateTimeZone('UTC');
        $today = $this->app->clock->now()->setTimezone($tz)->setTime(0, 0);
        $from = \DateTimeImmutable::createFromFormat('!Y-m-d', $req->q('from'), $tz) ?: $today->modify('-29 days');
        $to = \DateTimeImmutable::createFromFormat('!Y-m-d', $req->q('to'), $tz) ?: $today;
        $range = [$from->setTimezone($utc), $to->modify('+1 day')->setTimezone($utc)];
        $u = $this->app->user;
        $out = ['fromLabel' => $from->format('Y-m-d'), 'toLabel' => $to->format('Y-m-d'), 'sales' => [], 'daily' => [], 'countries' => [], 'inventory' => [], 'fulfilment' => [], 'returns' => [], 'adjustments' => []];

        if ($u->can('reports.sales', anyBrand: true)) {
            $os = $this->sqlScope($this->app->scope('reports.sales'), 's.brand_id');
            $paid = "o.payment_status IN ('paid','refunded','partially_refunded')";
            foreach ($this->db->all(
                "SELECT s.brand_id, o.currency, COUNT(*) orders, SUM(o.total_minor) gross, SUM(o.discount_minor) discounts, SUM(o.refunded_minor) refunds, SUM(o.tax_minor) tax, SUM(o.shipping_minor) shipping
                 FROM orders o JOIN stores s ON s.id = o.store_id WHERE {$os} AND {$paid} AND o.placed_at >= ? AND o.placed_at < ? GROUP BY s.brand_id, o.currency ORDER BY s.brand_id, o.currency",
                $range,
            ) as $r) {
                $out['sales'][] = ['brand' => $this->app->services->brands()[(int) $r['brand_id']]['name'], 'currency' => $r['currency'], 'orders' => (int) $r['orders'],
                    'gross' => (int) $r['gross'], 'discounts' => (int) $r['discounts'], 'refunds' => (int) $r['refunds'], 'net' => (int) $r['gross'] - (int) $r['refunds'], 'tax' => (int) $r['tax'], 'shipping' => (int) $r['shipping']];
            }
            $out['daily'] = $this->db->all(
                "SELECT DATE(CONVERT_TZ(o.placed_at, '+00:00', ?)) day, COUNT(*) orders FROM orders o JOIN stores s ON s.id = o.store_id WHERE {$os} AND {$paid} AND o.placed_at >= ? AND o.placed_at < ? GROUP BY day ORDER BY day",
                [$from->format('P'), ...$range],
            );
            $out['countries'] = $this->db->all(
                "SELECT COALESCE(o.ship_country, '—') country, o.currency, COUNT(*) orders, SUM(o.total_minor) gross FROM orders o JOIN stores s ON s.id = o.store_id
                 WHERE {$os} AND {$paid} AND o.placed_at >= ? AND o.placed_at < ? GROUP BY o.ship_country, o.currency ORDER BY orders DESC LIMIT 50",
                $range,
            );
        }
        if ($u->can('reports.operations', anyBrand: true)) {
            $inv = $this->app->services->inventory();
            $scope = $this->app->scope('reports.operations');
            $t = $inv->totals(null, $scope);
            $out['inventory'] = [['skus' => $t['skus'], 'units_on_hand' => $t['on_hand'], 'units_available' => $t['available'], 'low_stock_skus' => $t['low_stock'], 'out_of_stock_skus' => $t['out_of_stock']]];
            $out['adjustments'] = $this->db->all(
                "SELECT m.type, COUNT(*) movements, SUM(m.d_on_hand) on_hand_change, SUM(m.d_damaged) damaged_change FROM stock_movements m WHERE m.created_at >= ? AND m.created_at < ? AND (m.type LIKE 'adjust.%' OR m.type IN ('stocktake','damaged.write_off','purchase.receive','return.restock','return.damaged')) GROUP BY m.type ORDER BY m.type",
                $range,
            );
            $os = $this->sqlScope($scope, 's.brand_id');
            $f = $this->db->one(
                "SELECT SUM(o.payment_status = 'paid' AND o.fulfilment_status IN ('unfulfilled','processing','picked')) awaiting_processing,
                        SUM(o.payment_status = 'paid' AND o.fulfilment_status = 'packed') awaiting_dispatch,
                        (SELECT COUNT(*) FROM shipments sh JOIN orders o2 ON o2.id = sh.order_id JOIN stores s2 ON s2.id = o2.store_id WHERE " . str_replace('s.', 's2.', $os) . " AND sh.status IN ('dispatched','in_transit','exception')) in_transit,
                        (SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE, o3.placed_at, sh.dispatched_at)) / 60, 1) FROM shipments sh JOIN orders o3 ON o3.id = sh.order_id JOIN stores s3 ON s3.id = o3.store_id
                          WHERE " . str_replace('s.', 's3.', $os) . " AND sh.dispatched_at >= ? AND sh.dispatched_at < ?) avg_hours_to_dispatch
                 FROM orders o JOIN stores s ON s.id = o.store_id WHERE {$os}",
                $range,
            );
            $out['fulfilment'] = [array_map(fn ($v) => $v ?? 0, $f)];
            $out['returns'] = $this->db->all(
                "SELECT b.name brand, COUNT(DISTINCT r.id) returns, COALESCE(SUM(rl.qty), 0) units, COALESCE(SUM(rl.qty_damaged), 0) damaged_units
                 FROM returns r JOIN orders o ON o.id = r.order_id JOIN stores s ON s.id = o.store_id JOIN brands b ON b.id = s.brand_id LEFT JOIN return_lines rl ON rl.return_id = r.id
                 WHERE {$os} AND r.created_at >= ? AND r.created_at < ? GROUP BY b.id ORDER BY b.id",
                $range,
            );
        }
        if ($out['sales'] === [] && $out['inventory'] === [] && !$u->can('reports.sales', anyBrand: true) && !$u->can('reports.operations', anyBrand: true)) {
            $u->require('reports.sales', anyBrand: true);
        }
        return $out;
    }

    // ── Team ────────────────────────────────────────────────────────────────

    public function team(Request $req): Response
    {
        $this->app->user->require('team.manage');
        $users = $this->db->all('SELECT * FROM users ORDER BY status, name');
        $roles = [];
        foreach ($this->db->all('SELECT * FROM user_roles ORDER BY role') as $r) {
            $roles[$r['user_id']][] = $r;
        }
        return $this->app->page($req, 'team', ['title' => 'Team & permissions', 'nav' => 'team', 'users' => $users, 'roles' => $roles, 'setupLink' => $this->app->session->get('setup_link')]);
    }

    public function invite(Request $req): Response
    {
        $this->app->user->require('team.manage');
        $email = strtolower($req->p('email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $req->p('name') === '') {
            throw new LedgerException('Enter a name and a valid email address.');
        }
        $role = $req->p('role');
        if (!Roles::exists($role)) {
            throw new LedgerException('Choose a role.');
        }
        $brand = $req->int('brand_id', null, true);
        if ($this->db->value('SELECT 1 FROM users WHERE email = ?', [$email])) {
            throw new LedgerException('Someone with this email is already on the team.');
        }
        $token = bin2hex(random_bytes(32));
        $id = $this->db->transaction(function () use ($email, $req, $role, $brand, $token) {
            $id = $this->db->insert('INSERT INTO users (email, name, password_hash, created_at, setup_token_hash, setup_expires_at) VALUES (?, ?, ?, ?, ?, ?)', [
                $email, $req->p('name'), password_hash(bin2hex(random_bytes(32)), AuthController::algo()), $this->app->clock->now(), hash('sha256', $token), $this->app->clock->now()->modify('+72 hours'),
            ]);
            $this->db->run('INSERT INTO user_roles (user_id, role, brand_id) VALUES (?, ?, ?)', [$id, $role, $brand]);
            return $id;
        });
        $this->app->services->audit()->log($this->actor(), 'user.invite', 'user', $id, ['role' => $role, 'brand_id' => $brand], $req->ip);
        $link = rtrim($this->app->env['APP_URL'] ?? '', '/') . '/setup?token=' . $token;
        $this->app->session->set('setup_link', ['name' => $req->p('name'), 'link' => $link]);
        return Response::redirect('/team');
    }

    public function setRoles(Request $req, array $p): Response
    {
        $this->app->user->require('team.manage');
        $uid = (int) $p['id'];
        $wanted = [];
        foreach ((array) ($req->post['roles'] ?? []) as $entry) {
            [$role, $brand] = array_pad(explode(':', (string) $entry, 2), 2, '');
            if (!Roles::exists($role)) {
                continue;
            }
            $wanted[] = [$role, $brand === '' ? null : (int) $brand];
        }
        if ($uid === $this->app->user->userId && !in_array(['owner', null], $wanted, true)) {
            throw new LedgerException('You cannot remove your own owner role. Ask another owner.');
        }
        $this->db->transaction(function () use ($uid, $wanted) {
            $this->db->run('DELETE FROM user_roles WHERE user_id = ?', [$uid]);
            foreach ($wanted as [$role, $brand]) {
                $this->db->run('INSERT IGNORE INTO user_roles (user_id, role, brand_id) VALUES (?, ?, ?)', [$uid, $role, $brand]);
            }
        });
        $this->app->services->audit()->log($this->actor(), 'user.roles', 'user', $uid, ['roles' => $wanted], $req->ip);
        $this->app->flash('success', 'Roles updated. They apply from the person\'s next page view.');
        return Response::redirect('/team');
    }

    public function setStatus(Request $req, array $p): Response
    {
        $this->app->user->require('team.manage');
        $uid = (int) $p['id'];
        if ($uid === $this->app->user->userId) {
            throw new LedgerException('You cannot disable your own account.');
        }
        $status = $req->p('status') === 'disabled' ? 'disabled' : 'active';
        $this->db->run('UPDATE users SET status = ? WHERE id = ?', [$status, $uid]);
        $this->app->services->audit()->log($this->actor(), 'user.' . $status, 'user', $uid, [], $req->ip);
        $this->app->flash('success', $status === 'disabled' ? 'Access removed. They are signed out on their next page view.' : 'Access restored.');
        return Response::redirect('/team');
    }

    // ── Settings ────────────────────────────────────────────────────────────

    public function settings(Request $req): Response
    {
        $this->app->user->require('settings.manage');
        $markets = null;
        $marketError = null;
        try {
            $markets = $this->app->services->markets()->data;
        } catch (\Throwable $e) {
            $marketError = $e->getMessage();
        }
        $env = $this->app->env;
        $secretStatus = [];
        foreach ($this->db->all('SELECT code FROM stores ORDER BY id') as $s) {
            $p = 'STORE_' . strtoupper(str_replace('-', '_', $s['code']));
            $secretStatus[$s['code']] = ['rest' => !empty($env["{$p}_WC_KEY"]) && !empty($env["{$p}_WC_SECRET"]), 'hub' => !empty($env["{$p}_HUB_SECRET"]), 'webhook' => !empty($env["{$p}_WEBHOOK_SECRET"])];
        }
        return $this->app->page($req, 'settings', [
            'title' => 'Settings', 'nav' => 'settings', 'stores' => $this->db->all('SELECT * FROM stores ORDER BY id'),
            'warehouse' => $this->db->one('SELECT * FROM warehouses WHERE id = 1'), 'markets' => $markets, 'marketError' => $marketError, 'secrets' => $secretStatus,
        ]);
    }

    public function updateStore(Request $req, array $p): Response
    {
        $this->app->user->require('settings.manage');
        $url = rtrim($req->p('base_url'), '/');
        if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'https://'))) {
            throw new LedgerException('Enter the storefront address starting with https://');
        }
        $status = in_array($req->p('status'), ['not_connected', 'connected', 'paused'], true) ? $req->p('status') : 'not_connected';
        $this->db->run('UPDATE stores SET base_url = ?, status = ? WHERE id = ?', [$url ?: null, $status, (int) $p['id']]);
        $this->app->services->audit()->log($this->actor(), 'store.update', 'store', (int) $p['id'], ['base_url' => $url, 'status' => $status], $req->ip);
        $this->app->flash('success', 'Storefront settings saved.');
        return Response::redirect('/settings');
    }

    public function updateWarehouse(Request $req): Response
    {
        $this->app->user->require('settings.manage');
        $fields = ['line1', 'line2', 'city', 'state', 'postcode', 'contact_name', 'contact_phone', 'pickup_hours'];
        $addr = [];
        foreach ($fields as $f) {
            $addr[$f] = mb_substr($req->p($f), 0, 200);
        }
        if ($addr['line1'] === '' || !preg_match('/^\d{6}$/', $addr['postcode'])) {
            throw new LedgerException('Enter the street address and the 6-digit PIN code.');
        }
        $this->db->run("UPDATE warehouses SET address_json = ?, city = ? WHERE id = 1", [json_encode($addr + ['country' => 'IN']), $addr['city'] ?: 'Delhi']);
        $this->app->services->audit()->log($this->actor(), 'warehouse.update', 'warehouse', 1, [], $req->ip);
        $this->app->flash('success', 'Warehouse details saved.');
        return Response::redirect('/settings');
    }

    // ── Integration health ──────────────────────────────────────────────────

    public function health(Request $req): Response
    {
        $this->app->user->require('integrations.view');
        return $this->app->page($req, 'health', [
            'title' => 'Integration health', 'nav' => 'health',
            'stores' => $this->db->all('SELECT * FROM stores ORDER BY id'),
            'inbox' => $this->db->all("SELECT id, source, topic, event_key, attempts, last_error, received_at FROM inbox_events WHERE status = 'failed' ORDER BY id DESC LIMIT 100"),
            'dead' => $this->db->all("SELECT id, topic, attempts, last_error, updated_at, payload FROM outbox WHERE status = 'dead' ORDER BY id DESC LIMIT 100"),
            'pending' => (int) $this->db->value("SELECT COUNT(*) FROM outbox WHERE status IN ('pending','processing')"),
            'oldestPending' => $this->db->value("SELECT MIN(created_at) FROM outbox WHERE status = 'pending'"),
            'unmapped' => (int) $this->db->value("SELECT COUNT(*) FROM listings WHERE status = 'unmapped'"),
            'exceptions' => $this->db->all("SELECT r.id, r.order_ref, r.exception, r.store_id, r.updated_at FROM reservations r WHERE r.exception IS NOT NULL ORDER BY r.id DESC LIMIT 50"),
            'reconcile' => $this->app->session->get('reconcile'),
            'lastRates' => $this->db->value('SELECT MAX(fetched_at) FROM fx_rates'),
        ]);
    }

    public function retryInbox(Request $req, array $p): Response
    {
        $this->app->user->require('integrations.retry');
        try {
            $this->app->services->sync()->retry((int) $p['id']);
            $this->app->flash('success', 'Processed successfully on retry.');
        } catch (\Throwable $e) {
            $this->app->flash('error', 'Still failing: ' . \Commerce\Core\Integration\Outbox::safeMessage($e));
        }
        return Response::redirect('/health');
    }

    public function retryOutbox(Request $req, array $p): Response
    {
        $this->app->user->require('integrations.retry');
        $this->app->services->outbox()->retry((int) $p['id']);
        $this->app->services->audit()->log($this->actor(), 'outbox.retry', 'outbox', (int) $p['id'], [], $req->ip);
        $this->app->flash('success', 'Queued again. The background worker will try within a minute.');
        return Response::redirect('/health');
    }

    public function reconcile(Request $req): Response
    {
        $this->app->user->require('integrations.retry');
        $problems = (new Reconciler($this->db))->problems();
        $this->app->session->set('reconcile', ['at' => $this->app->clock->now()->format('Y-m-d H:i:s'), 'problems' => $problems]);
        $this->app->services->audit()->log($this->actor(), 'ledger.reconcile', 'ledger', 'all', ['problems' => count($problems)], $req->ip);
        return Response::redirect('/health');
    }
}
