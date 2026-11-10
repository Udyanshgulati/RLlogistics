<?php
/**
 * Storefront mode: this shop sells from the hub's central stock. Paid orders (and cancellations) are sent to the
 * hub in the background, retried if the hub cannot be reached; stock is read back from the hub every 5 minutes and
 * after every order, so every storefront shows the same numbers. Packing and dispatch happen at the hub, which
 * updates the order here.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

function tli_store_conf(): array
{
    return ['hub' => (string) get_option('tli_hub_url', ''), 'id' => (string) get_option('tli_store_id', ''), 'secret' => (string) get_option('tli_store_secret', '')];
}

function tli_store_ready(): bool
{
    $c = tli_store_conf();
    return tli_mode() === 'store' && $c['hub'] !== '' && $c['id'] !== '' && $c['secret'] !== '';
}

function tli_store_call(string $method, string $route, ?array $data = null): array
{
    $c = tli_store_conf();
    return tli_call($c['hub'], $c['id'], $c['secret'], $method, $route, $data);
}

/** Sets local stock to the hub's numbers (without logging them as local edits). */
function tli_apply_hub_stock(array $stock): int
{
    $n = 0;
    foreach ($stock as $sku => $qty) {
        $id = wc_get_product_id_by_sku((string) $sku);
        $p = $id ? wc_get_product($id) : null;
        if (!$p) {
            continue;
        }
        tli_without_auto_log(static function () use ($p, $qty, &$n): void {
            if ($qty === null) {
                if ($p->managing_stock()) {
                    $p->set_manage_stock(false);
                    $p->set_stock_status('instock');
                    $p->save();
                    $n++;
                }
                return;
            }
            if (!$p->managing_stock()) {
                $p->set_manage_stock(true);
                $p->save();
            }
            if ((int) $p->get_stock_quantity() !== (int) $qty) {
                wc_update_product_stock($p, (int) $qty, 'set');
                $n++;
            }
        });
    }
    return $n;
}

function tli_pull_stock(): void
{
    if (!tli_store_ready()) {
        return;
    }
    try {
        $r = tli_store_call('GET', 'stock');
        $n = tli_apply_hub_stock($r['stock'] ?? []);
        update_option('tli_last_sync', ['at' => time(), 'changed' => $n, 'error' => ''], false);
    } catch (Throwable $e) {
        update_option('tli_last_sync', ['at' => time(), 'changed' => 0, 'error' => $e->getMessage()], false);
    }
}

function tli_order_payload(WC_Order $o): array
{
    $lines = [];
    foreach ($o->get_items() as $item) {
        $p = $item->get_product();
        $lines[] = ['sku' => $p ? (string) $p->get_sku() : '', 'qty' => (int) $item->get_quantity(), 'name' => $item->get_name()];
    }
    return ['remote_id' => (string) $o->get_id(), 'number' => (string) $o->get_order_number(), 'status' => $o->get_status(), 'currency' => $o->get_currency(),
        'total' => (float) $o->get_total(), 'country' => $o->get_shipping_country() ?: $o->get_billing_country(), 'customer' => trim($o->get_formatted_billing_full_name()),
        'placed_at' => $o->get_date_created() ? gmdate('Y-m-d H:i:s', $o->get_date_created()->getTimestamp()) : current_time('mysql', true), 'lines' => $lines];
}

/** Background job: send one order to the hub. Throwing makes Action Scheduler record the failure; we retry with backoff. */
function tli_push_order(int $order_id, int $attempt = 1): void
{
    $o = wc_get_order($order_id);
    if (!$o || !tli_store_ready()) {
        return;
    }
    try {
        $r = tli_store_call('POST', 'orders', tli_order_payload($o));
        tli_apply_hub_stock($r['stock'] ?? []);
        $o->update_meta_data('_tli_hub_sent', $o->get_status());
        $o->save_meta_data();
        if (!empty($r['problems'])) {
            $o->add_order_note('Hub: ' . implode('; ', $r['problems']));
        }
        delete_option('tli_push_error');
    } catch (Throwable $e) {
        update_option('tli_push_error', ['order' => $order_id, 'at' => time(), 'error' => $e->getMessage(), 'attempt' => $attempt], false);
        if ($attempt < 8 && function_exists('as_schedule_single_action')) {
            as_schedule_single_action(time() + min(3600, 60 * 2 ** ($attempt - 1)), 'tli_push_order', [$order_id, $attempt + 1], 'tl-commerce-hub');
        }
        throw $e;
    }
}
add_action('tli_push_order', 'tli_push_order', 10, 2);
add_action('tli_pull_stock', 'tli_pull_stock');

// Every status change is sent (the hub takes stock once when paid and returns it if cancelled).
add_action('woocommerce_order_status_changed', static function ($id, $from, $to): void {
    if (!tli_store_ready() || !empty($GLOBALS['tli_from_hub'])) {
        return;
    }
    if (in_array($to, TLI_PAID, true) || in_array($to, TLI_RELEASED, true)) {
        as_enqueue_async_action('tli_push_order', [(int) $id, 1], 'tl-commerce-hub');
    }
}, 10, 3);
add_action('init', static function (): void {
    if (tli_store_ready() && function_exists('as_has_scheduled_action') && !as_has_scheduled_action('tli_pull_stock', [], 'tl-commerce-hub')) {
        as_schedule_recurring_action(time() + 30, 5 * MINUTE_IN_SECONDS, 'tli_pull_stock', [], 'tl-commerce-hub');
    }
});

// The hub tells this store when it has packed, dispatched or delivered an order.
add_action('rest_api_init', static function (): void {
    if (tli_mode() !== 'store') {
        return;
    }
    register_rest_route('tlhub/v1', '/stage', ['methods' => 'POST', 'permission_callback' => static function (WP_REST_Request $r) {
        $c = tli_store_conf();
        return tli_store_ready() && hash_equals($c['id'], (string) $r->get_header('x_tl_store')) && tli_verify($r, $c['secret'])
            ? true : new WP_Error('tl_auth', 'Signature invalid.', ['status' => 401]);
    }, 'callback' => static function (WP_REST_Request $r) {
        $d = $r->get_json_params() ?: [];
        $o = wc_get_order(absint($d['remote_id'] ?? 0));
        $to = sanitize_key((string) ($d['status'] ?? ''));
        if (!$o || !in_array($to, ['tl-packed', 'tl-dispatched', 'completed'], true)) {
            return new WP_Error('tl_bad', 'Unknown order or stage.', ['status' => 400]);
        }
        $GLOBALS['tli_from_hub'] = true;
        $o->update_status($to, 'Updated by the hub: ' . TLI_STAGES[$to][0] . '.');
        $GLOBALS['tli_from_hub'] = false;
        return ['ok' => true];
    }]);
});

/* Connection screen. */
add_action('admin_post_tli_connection', static function (): void {
    if (!current_user_can('manage_woocommerce')) {
        wp_die('Not allowed.', 403);
    }
    check_admin_referer('tli_connection');
    $mode = sanitize_key($_POST['mode'] ?? 'hub') === 'store' ? 'store' : 'hub';
    update_option('tli_mode', $mode);
    $msg = $mode === 'hub' ? 'This site is the hub.' : '';
    $ok = '1';
    if ($mode === 'store') {
        update_option('tli_hub_url', untrailingslashit(esc_url_raw(wp_unslash($_POST['hub'] ?? ''))));
        update_option('tli_store_id', sanitize_text_field(wp_unslash($_POST['store_id'] ?? '')));
        if (!empty($_POST['secret'])) {
            update_option('tli_store_secret', sanitize_text_field(wp_unslash($_POST['secret'])), false);
        }
        try {
            $r = tli_store_call('GET', 'ping');
            tli_pull_stock();
            $msg = sprintf('Connected to %s as %s. Stock synced.', $r['hub'] ?? 'the hub', $r['store'] ?? 'this store');
        } catch (Throwable $e) {
            $msg = 'Saved, but the hub could not be reached: ' . $e->getMessage();
            $ok = '0';
        }
    }
    wp_safe_redirect(add_query_arg(['tli_msg' => rawurlencode($msg), 'tli_ok' => $ok], admin_url('admin.php?page=tl-connection')));
    exit;
});
add_action('admin_post_tli_sync_now', static function (): void {
    if (!current_user_can('manage_woocommerce')) {
        wp_die('Not allowed.', 403);
    }
    check_admin_referer('tli_sync_now');
    tli_pull_stock();
    $s = get_option('tli_last_sync', []);
    $msg = !empty($s['error']) ? 'Sync failed: ' . $s['error'] : sprintf('Stock synced from the hub (%d sizes changed).', (int) ($s['changed'] ?? 0));
    wp_safe_redirect(add_query_arg(['tli_msg' => rawurlencode($msg), 'tli_ok' => empty($s['error']) ? '1' : '0'], admin_url('admin.php?page=tl-connection')));
    exit;
});

function tli_page_connection(): void
{
    $c = tli_store_conf();
    $mode = tli_mode();
    tli_head('Connection', 'Choose whether this site is the central hub or a storefront that sells from the hub.');
    echo '<div class="tli-card"><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('tli_connection');
    echo '<input type="hidden" name="action" value="tli_connection"><fieldset><legend>This site is</legend>';
    printf('<label class="tli-radio"><input type="radio" name="mode" value="hub"%s> <span><b>The hub</b> — holds the central stock; other stores connect to it (Stores page).</span></label>', checked($mode, 'hub', false));
    printf('<label class="tli-radio"><input type="radio" name="mode" value="store"%s> <span><b>A storefront</b> — sells from the hub\'s stock and sends its orders there.</span></label>', checked($mode, 'store', false));
    printf('</fieldset><div class="tli-store-fields"><label class="tli-field"><span>Hub address</span><input class="tli-input" type="url" name="hub" value="%s" placeholder="https://schermo.thodalogic.com"></label><label class="tli-field"><span>Store ID</span><input class="tli-input" name="store_id" value="%s" placeholder="st_…"></label><label class="tli-field"><span>Secret key</span><input class="tli-input" type="password" name="secret" placeholder="%s" autocomplete="off"></label></div>',
        esc_attr($c['hub']), esc_attr($c['id']), $c['secret'] !== '' ? esc_attr__('Saved — leave empty to keep') : '');
    echo '<p><button class="tli-btn primary">Save and test</button></p></form></div>';
    if ($mode === 'store') {
        $s = get_option('tli_last_sync', []);
        $e = get_option('tli_push_error', []);
        printf('<div class="tli-card"><h2>Sync health</h2><ul class="tli-help"><li>Last stock sync: %s%s</li><li>Order sending: %s</li></ul><form method="post" action="%s"><input type="hidden" name="action" value="tli_sync_now">%s<button class="tli-btn">Sync stock now</button></form></div>',
            !empty($s['at']) ? esc_html(human_time_diff((int) $s['at']) . ' ago') : 'never', !empty($s['error']) ? ' — <b>' . esc_html($s['error']) . '</b>' : '',
            $e ? sprintf('order #%d not delivered yet (attempt %d, retrying automatically): %s', (int) $e['order'], (int) $e['attempt'], esc_html($e['error'])) : 'all orders delivered to the hub',
            esc_url(admin_url('admin-post.php')), wp_nonce_field('tli_sync_now', '_wpnonce', true, false));
    }
    tli_foot();
}
