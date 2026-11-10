<?php
/**
 * Hub mode: this site holds the central stock. Connected storefronts send their paid orders here (stock is taken
 * once per order) and read stock back. The warehouse moves remote orders through Packed → Dispatched → Delivered
 * here, and each change is sent back to the storefront.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

function tli_stores(bool $active_only = false): array
{
    global $wpdb;
    $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}tl_stores ORDER BY created_at", ARRAY_A) ?: [];
    return $active_only ? array_values(array_filter($rows, fn ($s) => (int) $s['active'] === 1)) : $rows;
}

function tli_store(string $id): ?array
{
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tl_stores WHERE id = %s", $id), ARRAY_A) ?: null;
}

function tli_store_seen(string $id, string $error = ''): void
{
    global $wpdb;
    $wpdb->update($wpdb->prefix . 'tl_stores', ['last_seen' => current_time('mysql', true), 'last_error' => mb_substr($error, 0, 250)], ['id' => $id]);
}

/** Current central stock: SKU => int, or null where the size is not counted yet (sold without a limit). */
function tli_stock_map(array $skus = []): array
{
    $map = [];
    foreach (tli_inventory_rows() as $g) {
        foreach ($g['rows'] as $r) {
            if ($r['sku'] !== '' && (!$skus || in_array($r['sku'], $skus, true))) {
                $map[$r['sku']] = $r['qty'];
            }
        }
    }
    return $map;
}

/** Takes (sign -1) or returns (+1) the stock for a remote order's lines, recording each movement. */
function tli_move_remote_stock(array $store, array $order, int $sign): array
{
    $problems = [];
    foreach (json_decode($order['lines_json'], true) ?: [] as $l) {
        $id = wc_get_product_id_by_sku((string) $l['sku']);
        $p = $id ? wc_get_product($id) : null;
        if (!$p) {
            $problems[] = "SKU {$l['sku']} is not in the hub catalogue";
            continue;
        }
        if (!$p->managing_stock()) {
            continue; // not counted yet: nothing to take
        }
        $before = (int) $p->get_stock_quantity();
        $new = tli_without_auto_log(static fn () => wc_update_product_stock($p, (int) $l['qty'], $sign < 0 ? 'decrease' : 'increase'));
        tli_log($p, (int) $new - $before, $before, (int) $new, $sign < 0 ? 'sold' : 'order_restored', $store['name'] . ' order #' . $order['number']);
        if ($new < 0) {
            $problems[] = "{$l['sku']} oversold (now {$new})";
        }
    }
    return $problems;
}

add_action('rest_api_init', static function (): void {
    if (tli_mode() !== 'hub') {
        return;
    }
    $auth = static function (WP_REST_Request $r) {
        $store = tli_store((string) $r->get_header('x_tl_store'));
        if (!$store || !(int) $store['active'] || !tli_verify($r, $store['secret'])) {
            return new WP_Error('tl_auth', 'Store not recognised or signature invalid.', ['status' => 401]);
        }
        $r->set_param('_tl_store', $store);
        return true;
    };
    register_rest_route('tlhub/v1', '/ping', ['methods' => 'GET', 'permission_callback' => $auth, 'callback' => static function (WP_REST_Request $r) {
        $s = $r->get_param('_tl_store');
        tli_store_seen($s['id']);
        return ['ok' => true, 'hub' => get_bloginfo('name'), 'store' => $s['name']];
    }]);
    register_rest_route('tlhub/v1', '/stock', ['methods' => 'GET', 'permission_callback' => $auth, 'callback' => static function (WP_REST_Request $r) {
        tli_store_seen($r->get_param('_tl_store')['id']);
        return ['ok' => true, 'stock' => tli_stock_map()];
    }]);
    register_rest_route('tlhub/v1', '/orders', ['methods' => 'POST', 'permission_callback' => $auth, 'callback' => static function (WP_REST_Request $r) {
        global $wpdb;
        $store = $r->get_param('_tl_store');
        $d = $r->get_json_params() ?: [];
        $rid = sanitize_text_field((string) ($d['remote_id'] ?? ''));
        if ($rid === '' || !is_array($d['lines'] ?? null)) {
            return new WP_Error('tl_bad', 'remote_id and lines are required.', ['status' => 400]);
        }
        $status = sanitize_key((string) ($d['status'] ?? 'processing'));
        $lines = array_values(array_map(static fn ($l) => ['sku' => sanitize_text_field((string) ($l['sku'] ?? '')), 'qty' => max(0, (int) ($l['qty'] ?? 0)), 'name' => sanitize_text_field((string) ($l['name'] ?? ''))], $d['lines']));
        $t = $wpdb->prefix . 'tl_remote_orders';
        $now = current_time('mysql', true);
        // Insert once per (store, order); a repeat send is an update. The unique key makes this safe to retry.
        $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$t} (store_id, remote_id, number, status, currency, total, country, customer, lines_json, placed_at, updated_at) VALUES (%s,%s,%s,%s,%s,%f,%s,%s,%s,%s,%s)",
            $store['id'], $rid, sanitize_text_field((string) ($d['number'] ?? $rid)), $status, strtoupper(substr(sanitize_key((string) ($d['currency'] ?? 'INR')), 0, 3)), (float) ($d['total'] ?? 0),
            strtoupper(substr(sanitize_key((string) ($d['country'] ?? '')), 0, 2)), sanitize_text_field((string) ($d['customer'] ?? '')), wp_json_encode($lines), sanitize_text_field((string) ($d['placed_at'] ?? $now)), $now));
        // Serialise stock decisions for this order (row lock) so two sends of the same order cannot both take stock.
        $wpdb->query('START TRANSACTION');
        $o = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE store_id = %s AND remote_id = %s FOR UPDATE", $store['id'], $rid), ARRAY_A);
        $problems = [];
        $shipping_stage = in_array($o['status'], ['tl-packed', 'tl-dispatched', 'completed'], true);
        if (!$o['stock_taken'] && in_array($status, TLI_PAID, true)) {
            $problems = tli_move_remote_stock($store, $o, -1);
            $wpdb->update($t, ['stock_taken' => 1], ['id' => $o['id']]);
        } elseif ($o['stock_taken'] && in_array($status, TLI_RELEASED, true)) {
            $problems = tli_move_remote_stock($store, $o, +1);
            $wpdb->update($t, ['stock_taken' => 0], ['id' => $o['id']]);
        }
        // The hub owns the shipping stages; a storefront's own "processing" never moves an order back.
        $keep = $shipping_stage && $status === 'processing';
        $wpdb->update($t, ['status' => $keep ? $o['status'] : $status, 'updated_at' => $now, 'lines_json' => wp_json_encode($lines),
            'ship_to' => sanitize_textarea_field((string) ($d['ship_to'] ?? '')), 'phone' => sanitize_text_field((string) ($d['phone'] ?? '')), 'note' => sanitize_textarea_field((string) ($d['note'] ?? ''))], ['id' => $o['id']]);
        $wpdb->query('COMMIT');
        tli_store_seen($store['id'], $problems ? implode('; ', $problems) : '');
        $skus = array_column($lines, 'sku');
        return ['ok' => true, 'problems' => $problems, 'stock' => tli_stock_map($skus)];
    }]);
});

/** The warehouse moves a remote order to its next stage; the storefront is told straight away. */
add_action('admin_post_tli_remote_next', static function (): void {
    global $wpdb;
    if (!current_user_can('tl_fulfil')) {
        wp_die('Not allowed.', 403);
    }
    $id = absint($_POST['remote'] ?? 0);
    check_admin_referer('tli_remote_' . $id);
    $t = $wpdb->prefix . 'tl_remote_orders';
    $o = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d", $id), ARRAY_A);
    $msg = 'Order not found.';
    $ok = '0';
    if ($o && isset(TLI_NEXT[$o['status']]) && ($store = tli_store($o['store_id']))) {
        [$to, $label] = TLI_NEXT[$o['status']];
        $track = [];
        if ($to === 'tl-dispatched') {
            $tr = tli_tracking_from_post();
            if (!$tr) {
                wp_safe_redirect(add_query_arg(['tli_msg' => rawurlencode('Enter the courier and AWB number to mark the order as dispatched.'), 'tli_ok' => '0'], wp_get_referer() ?: admin_url('admin.php?page=tl-orders')));
                exit;
            }
            $track = ['courier' => $tr['courier'], 'awb' => $tr['awb'], 'track_url' => $tr['url']];
        }
        $wpdb->update($t, ['status' => $to, 'updated_at' => current_time('mysql', true)] + $track, ['id' => $id]);
        try {
            tli_call($store['url'], $store['id'], $store['secret'], 'POST', 'stage', ['remote_id' => $o['remote_id'], 'status' => $to] + $track);
            tli_store_seen($store['id']);
            $msg = sprintf('%s order #%s: %s.', $store['name'], $o['number'], TLI_STAGES[$to][0]);
            $ok = '1';
        } catch (Throwable $e) {
            tli_store_seen($store['id'], 'Stage update not delivered: ' . $e->getMessage());
            $msg = sprintf('Saved here, but %s was not updated: %s', $store['name'], $e->getMessage());
        }
    }
    wp_safe_redirect(add_query_arg(['tli_msg' => rawurlencode($msg), 'tli_ok' => $ok], wp_get_referer() ?: admin_url('admin.php?page=tl-orders')));
    exit;
});

/* Stores screen (hub mode). */
add_action('admin_post_tli_add_store', static function (): void {
    global $wpdb;
    if (!current_user_can('tl_manage')) {
        wp_die('Not allowed.', 403);
    }
    check_admin_referer('tli_add_store');
    $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $url = esc_url_raw(wp_unslash($_POST['url'] ?? ''));
    if ($name === '' || !wp_http_validate_url($url)) {
        wp_safe_redirect(add_query_arg(['tli_msg' => rawurlencode('Enter a store name and its full web address (https://…).'), 'tli_ok' => '0'], admin_url('admin.php?page=tl-stores')));
        exit;
    }
    $id = 'st_' . strtolower(wp_generate_password(10, false));
    $secret = wp_generate_password(48, false);
    $wpdb->insert($wpdb->prefix . 'tl_stores', ['id' => $id, 'name' => $name, 'url' => untrailingslashit($url), 'secret' => $secret, 'active' => 1, 'created_at' => current_time('mysql', true)]);
    set_transient('tli_new_store_' . get_current_user_id(), ['id' => $id, 'secret' => $secret, 'name' => $name], 15 * MINUTE_IN_SECONDS);
    wp_safe_redirect(admin_url('admin.php?page=tl-stores&new=1'));
    exit;
});
add_action('admin_post_tli_store_toggle', static function (): void {
    global $wpdb;
    if (!current_user_can('tl_manage')) {
        wp_die('Not allowed.', 403);
    }
    $id = sanitize_text_field(wp_unslash($_POST['store'] ?? ''));
    check_admin_referer('tli_store_toggle_' . $id);
    $s = tli_store($id);
    if ($s) {
        $wpdb->update($wpdb->prefix . 'tl_stores', ['active' => (int) $s['active'] ? 0 : 1], ['id' => $id]);
    }
    wp_safe_redirect(admin_url('admin.php?page=tl-stores'));
    exit;
});

function tli_page_stores(): void
{
    global $wpdb;
    tli_head('Stores', 'Storefronts connected to this hub. Each sends its paid orders here and reads stock from here.');
    $new = !empty($_GET['new']) ? get_transient('tli_new_store_' . get_current_user_id()) : null; // phpcs:ignore WordPress.Security.NonceVerification
    if ($new) {
        delete_transient('tli_new_store_' . get_current_user_id());
        printf('<div class="tli-card tli-keys"><h2>%s is ready to connect</h2><p class="muted">On that store: install TL Commerce Hub, open <b>TL Commerce Hub → Connection</b>, choose <b>Storefront</b> and paste these. The secret is shown only now.</p><dl><dt>Hub address</dt><dd><code>%s</code></dd><dt>Store ID</dt><dd><code>%s</code></dd><dt>Secret key</dt><dd><code>%s</code></dd></dl></div>',
            esc_html($new['name']), esc_html(home_url()), esc_html($new['id']), esc_html($new['secret']));
    }
    $counts = $wpdb->get_results("SELECT store_id, COUNT(*) n FROM {$wpdb->prefix}tl_remote_orders GROUP BY store_id", OBJECT_K) ?: [];
    echo '<div class="tli-card flush"><table class="tli-table"><thead><tr><th>Store</th><th>Address</th><th>Status</th><th>Last contact</th><th class="num">Orders</th><th>Last problem</th><th></th></tr></thead><tbody>';
    printf('<tr><td><b>%s</b><span class="muted small"> · this site (hub)</span></td><td class="small">%s</td><td>%s</td><td class="small">—</td><td class="num">%d</td><td></td><td></td></tr>',
        esc_html(get_bloginfo('name')), esc_html(home_url()), tli_badge('Hub', 'info'), count(wc_get_orders(['limit' => -1, 'return' => 'ids'])));
    foreach (tli_stores() as $s) {
        $seen = $s['last_seen'] ? strtotime($s['last_seen'] . ' UTC') : 0;
        $state = !(int) $s['active'] ? tli_badge('Paused', 'neutral') : (!$seen ? tli_badge('Not connected yet', 'warn') : ($s['last_error'] !== '' ? tli_badge('Needs attention', 'bad') : (time() - $seen < 900 ? tli_badge('Connected', 'ok') : tli_badge('Quiet', 'warn'))));
        printf('<tr><td><b>%s</b><span class="mono">%s</span></td><td class="small"><a href="%s" target="_blank" rel="noopener">%s</a></td><td>%s</td><td class="small">%s</td><td class="num">%d</td><td class="small">%s</td><td class="num"><form method="post" action="%s"><input type="hidden" name="action" value="tli_store_toggle"><input type="hidden" name="store" value="%s">%s<button class="tli-btn sm">%s</button></form></td></tr>',
            esc_html($s['name']), esc_html($s['id']), esc_url($s['url']), esc_html($s['url']), $state, $seen ? esc_html(human_time_diff($seen) . ' ago') : '—', (int) ($counts[$s['id']]->n ?? 0), esc_html($s['last_error']),
            esc_url(admin_url('admin-post.php')), esc_attr($s['id']), wp_nonce_field('tli_store_toggle_' . $s['id'], '_wpnonce', true, false), (int) $s['active'] ? 'Pause' : 'Resume');
    }
    echo '</tbody></table></div>';
    echo '<div class="tli-card"><h2>Connect a storefront</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="tli-inline">';
    wp_nonce_field('tli_add_store');
    echo '<input type="hidden" name="action" value="tli_add_store"><label class="tli-field"><span>Store name</span><input class="tli-input" name="name" placeholder="e.g. Significant Sports" required></label><label class="tli-field"><span>Web address</span><input class="tli-input" name="url" type="url" placeholder="https://…" required></label><button class="tli-btn primary">Create connection key</button></form></div>';
    tli_foot();
}

function tli_remote_orders(string $stage = '', int $limit = 50): array
{
    global $wpdb;
    $t = $wpdb->prefix . 'tl_remote_orders';
    $sql = $stage !== '' ? $wpdb->prepare("SELECT * FROM {$t} WHERE status = %s ORDER BY placed_at DESC LIMIT %d", $stage, $limit)
        : $wpdb->prepare("SELECT * FROM {$t} ORDER BY placed_at DESC LIMIT %d", $limit);
    return $wpdb->get_results($sql, ARRAY_A) ?: [];
}
