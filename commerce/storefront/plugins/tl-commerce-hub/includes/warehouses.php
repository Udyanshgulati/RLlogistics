<?php
/**
 * Warehouses (hub only). Each warehouse has its own stock per size; WooCommerce's stock for a size is always the sum
 * of its warehouses, so the shop, the storefronts and every report keep working on one number.
 *
 * - A product can name the warehouse it ships from (its default). A paid order takes each line from that warehouse,
 *   or — when it has too few — from the store's default warehouse, then from whichever warehouse has the most. The
 *   split is saved on the order line (or on the other store's order), so a cancellation puts stock back where it
 *   came from, and pick lists and packing slips can be printed per warehouse.
 * - Stock changes made in TL Commerce Hub name a warehouse; changes made elsewhere (WooCommerce product screen, the
 *   client sheet import) are absorbed by the product's default warehouse.
 * - Moving stock between warehouses leaves the total unchanged and is logged as two movements.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

function tli_wh_tables(): array
{
    global $wpdb;
    return [$wpdb->prefix . 'tl_warehouses', $wpdb->prefix . 'tl_wh_stock'];
}

function tli_install_warehouses(): void
{
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    [$w, $s] = tli_wh_tables();
    $cs = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE {$w} (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        code varchar(12) NOT NULL,
        name varchar(100) NOT NULL,
        city varchar(100) NOT NULL DEFAULT '',
        country char(2) NOT NULL DEFAULT '',
        address text NULL,
        dispatch varchar(60) NOT NULL DEFAULT '',
        is_default tinyint(1) NOT NULL DEFAULT 0,
        active tinyint(1) NOT NULL DEFAULT 1,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id)
    ) {$cs};");
    dbDelta("CREATE TABLE {$s} (
        product_id bigint unsigned NOT NULL,
        warehouse_id bigint unsigned NOT NULL,
        qty int NOT NULL DEFAULT 0,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (product_id, warehouse_id),
        KEY warehouse_id (warehouse_id)
    ) {$cs};");
    // First run: the existing stock is all in one warehouse (the one the store has shipped from so far).
    if (!(int) $wpdb->get_var("SELECT COUNT(*) FROM {$w}")) {
        $wpdb->insert($w, ['code' => 'MAIN', 'name' => 'Main warehouse', 'city' => '', 'country' => 'IN', 'is_default' => 1, 'active' => 1, 'created_at' => current_time('mysql', true)]);
    }
    tli_wh_flush();
    if (tli_mode() === 'hub') {
        foreach (tli_inventory_rows() as $g) {
            foreach ($g['rows'] as $r) {
                if ($r['qty'] !== null && ($u = wc_get_product($r['id']))) {
                    tli_wh_reconcile($u);
                }
            }
        }
    }
}

/** Warehouses, default first. */
function tli_warehouses(bool $active_only = true): array
{
    global $wpdb;
    $k = (int) $active_only;
    if (!isset($GLOBALS['tli_wh_cache'][$k])) {
        [$w] = tli_wh_tables();
        $GLOBALS['tli_wh_cache'][$k] = $wpdb->get_results("SELECT * FROM {$w}" . ($active_only ? ' WHERE active = 1' : '') . ' ORDER BY is_default DESC, name ASC', ARRAY_A) ?: [];
    }
    return $GLOBALS['tli_wh_cache'][$k];
}

function tli_wh_flush(): void
{
    unset($GLOBALS['tli_wh_cache']);
}

function tli_wh_default(): int
{
    $all = tli_warehouses();
    return $all ? (int) $all[0]['id'] : 0;
}

function tli_wh(int $id): ?array
{
    foreach (tli_warehouses(false) as $w) {
        if ((int) $w['id'] === $id) {
            return $w;
        }
    }
    return null;
}

function tli_wh_multi(): bool
{
    return tli_mode() === 'hub' && count(tli_warehouses()) > 1;
}

/** Stock per warehouse for one size/unit: [warehouse_id => qty]. */
function tli_wh_qty(int $pid): array
{
    global $wpdb;
    [, $s] = tli_wh_tables();
    $out = [];
    foreach ($wpdb->get_results($wpdb->prepare("SELECT warehouse_id, qty FROM {$s} WHERE product_id = %d", $pid), ARRAY_A) ?: [] as $r) {
        $out[(int) $r['warehouse_id']] = (int) $r['qty'];
    }
    return $out;
}

function tli_wh_change(int $pid, int $wh, int $delta): int
{
    global $wpdb;
    [, $s] = tli_wh_tables();
    $wpdb->query($wpdb->prepare("INSERT INTO {$s} (product_id, warehouse_id, qty, updated_at) VALUES (%d, %d, %d, %s) ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty), updated_at = VALUES(updated_at)", $pid, $wh, $delta, current_time('mysql', true)));
    return (int) $wpdb->get_var($wpdb->prepare("SELECT qty FROM {$s} WHERE product_id = %d AND warehouse_id = %d", $pid, $wh));
}

/** The warehouse a product ships from: its own setting (on the product or its parent), else the default. */
function tli_wh_for(WC_Product $u): int
{
    $own = (int) $u->get_meta('_tl_warehouse');
    if (!$own && $u->get_parent_id()) {
        $own = (int) get_post_meta($u->get_parent_id(), '_tl_warehouse', true);
    }
    return $own && ($w = tli_wh($own)) && (int) $w['active'] ? $own : tli_wh_default();
}

/**
 * Makes the warehouse split add up to $total (default: WooCommerce's current stock). Stock that appeared elsewhere
 * goes to the product's warehouse; stock that disappeared is taken from its warehouse first, then the others.
 */
function tli_wh_reconcile(WC_Product $u, ?int $total = null): void
{
    if (tli_mode() !== 'hub' || !$u->managing_stock() || !tli_warehouses()) {
        return;
    }
    $total ??= (int) $u->get_stock_quantity();
    $have = tli_wh_qty($u->get_id());
    $diff = $total - array_sum($have);
    if ($diff === 0) {
        return;
    }
    $home = tli_wh_for($u);
    if ($diff > 0) {
        tli_wh_change($u->get_id(), $home, $diff);
        return;
    }
    $need = -$diff;
    foreach (tli_wh_order($have, $home) as $wh) {
        $take = min($need, max(0, $have[$wh] ?? 0));
        if ($take > 0) {
            tli_wh_change($u->get_id(), $wh, -$take);
            $need -= $take;
        }
        if ($need === 0) {
            break;
        }
    }
    if ($need > 0) {
        tli_wh_change($u->get_id(), $home, -$need); // oversold: shows as negative in its own warehouse
    }
}

/** Warehouses in the order they are used: the product's own, the default, then the one with the most stock. */
function tli_wh_order(array $have, int $home): array
{
    $ids = array_map(static fn ($w) => (int) $w['id'], tli_warehouses());
    $rest = array_values(array_diff($ids, [$home, tli_wh_default()]));
    usort($rest, static fn ($a, $b) => ($have[$b] ?? 0) <=> ($have[$a] ?? 0));
    return array_values(array_unique(array_filter(array_merge([$home, tli_wh_default()], $rest))));
}

/** Takes $qty of a unit for an order and returns the split [warehouse_id => qty]. */
function tli_wh_take(WC_Product $u, int $qty, int $before_total): array
{
    if (tli_mode() !== 'hub' || !$u->managing_stock() || $qty <= 0 || !tli_warehouses()) {
        return [];
    }
    tli_wh_reconcile($u, $before_total);
    $have = tli_wh_qty($u->get_id());
    $home = tli_wh_for($u);
    $plan = [];
    $need = $qty;
    // One warehouse that can send the whole line is preferred, so a line is not split without need.
    foreach (tli_wh_order($have, $home) as $wh) {
        if (($have[$wh] ?? 0) >= $need) {
            $plan[$wh] = $need;
            $need = 0;
            break;
        }
    }
    foreach ($need ? tli_wh_order($have, $home) : [] as $wh) {
        $take = min($need, max(0, $have[$wh] ?? 0));
        if ($take > 0) {
            $plan[$wh] = ($plan[$wh] ?? 0) + $take;
            $need -= $take;
        }
    }
    if ($need > 0) {
        $plan[$home] = ($plan[$home] ?? 0) + $need;
    }
    foreach ($plan as $wh => $n) {
        tli_wh_change($u->get_id(), (int) $wh, -$n);
    }
    return $plan;
}

/** Puts an order's split back. */
function tli_wh_give(WC_Product $u, array $plan, int $qty): void
{
    if (tli_mode() !== 'hub' || !$u->managing_stock() || !tli_warehouses()) {
        return;
    }
    $plan = array_filter(array_map('intval', $plan));
    if (!$plan) {
        $plan = [tli_wh_for($u) => $qty];
    }
    foreach ($plan as $wh => $n) {
        tli_wh_change($u->get_id(), (int) $wh, $n);
    }
}

/** "Main warehouse 3 · London 2" style label for a split. */
function tli_wh_label(array $plan): string
{
    $out = [];
    foreach ($plan as $wh => $n) {
        $w = tli_wh((int) $wh);
        $out[] = ($w ? $w['name'] : '#' . $wh) . (count($plan) > 1 ? ' ×' . (int) $n : '');
    }
    return implode(' · ', $out);
}

// ---- Orders on this site: split each line when WooCommerce takes the stock, put it back on cancel.
add_action('woocommerce_reduce_order_item_stock', static function ($item, $change, $order): void {
    $u = $item->get_product();
    if (!$u || !is_array($change) || tli_mode() !== 'hub') {
        return;
    }
    $plan = tli_wh_take($u, (int) $change['from'] - (int) $change['to'], (int) $change['from']);
    if ($plan) {
        $item->update_meta_data('_tl_alloc', $plan);
        $item->save_meta_data();
    }
}, 5, 3);
add_action('woocommerce_restore_order_item_stock', static function ($item, $new, $old, $order): void {
    $u = $item->get_product();
    if (!$u || tli_mode() !== 'hub') {
        return;
    }
    tli_wh_give($u, (array) $item->get_meta('_tl_alloc'), (int) $new - (int) $old);
    $item->delete_meta_data('_tl_alloc');
    $item->save_meta_data();
}, 5, 4);

// Stock typed into WooCommerce's own screens, or set by the client sheet import: the product's warehouse absorbs it.
foreach (['woocommerce_product_set_stock', 'woocommerce_variation_set_stock'] as $hook) {
    add_action($hook, static function (WC_Product $p): void {
        if (empty($GLOBALS['tli_quiet']) && empty($GLOBALS['tli_order_stock'])) {
            tli_wh_reconcile($p);
        }
    }, 20);
}
// While WooCommerce reduces/restores stock for an order, the order hooks above handle the split.
add_action('woocommerce_reduce_order_stock', static function (): void {
    $GLOBALS['tli_order_stock'] = false;
}, 99);
add_filter('woocommerce_can_reduce_order_stock', static function ($ok) {
    $GLOBALS['tli_order_stock'] = true;
    return $ok;
}, 99);
add_filter('woocommerce_can_restore_order_stock', static function ($ok) {
    $GLOBALS['tli_order_stock'] = true;
    return $ok;
}, 99);
add_action('woocommerce_restore_order_stock', static function (): void {
    $GLOBALS['tli_order_stock'] = false;
}, 99);

/** Order-level "ships from": the warehouses its lines were taken from. */
function tli_order_warehouses(WC_Order $o): array
{
    $ids = [];
    foreach ($o->get_items() as $item) {
        foreach (array_keys((array) $item->get_meta('_tl_alloc')) as $wh) {
            $ids[(int) $wh] = true;
        }
    }
    return array_keys($ids);
}

// ---- Admin: warehouses screen
add_action('admin_menu', static function (): void {
    if (tli_mode() === 'hub') {
        add_submenu_page('tl-inventory', 'Warehouses', 'Warehouses', 'tl_manage', 'tl-warehouses', 'tli_page_warehouses', 6);
    }
}, 12);

add_action('admin_post_tli_wh_save', static function (): void {
    global $wpdb;
    if (!current_user_can('tl_manage') || tli_mode() !== 'hub') {
        wp_die('Not allowed.', 403);
    }
    check_admin_referer('tli_wh_save');
    [$w, $s] = tli_wh_tables();
    $id = absint($_POST['id'] ?? 0);
    $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', sanitize_text_field(wp_unslash($_POST['code'] ?? ''))));
    $back = admin_url('admin.php?page=tl-warehouses');
    if ($name === '' || $code === '') {
        wp_safe_redirect(add_query_arg(['tli_msg' => rawurlencode('Give the warehouse a name and a short code.'), 'tli_ok' => '0'], $back));
        exit;
    }
    $active = !empty($_POST['active']) ? 1 : 0;
    $default = !empty($_POST['is_default']) ? 1 : 0;
    if ($id && !$active) {
        $units = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(qty), 0) FROM {$s} WHERE warehouse_id = %d", $id));
        if ($units !== 0 || ($default && $id)) {
            wp_safe_redirect(add_query_arg(['tli_msg' => rawurlencode($units ? "This warehouse still holds {$units} pieces — move them to another warehouse first." : 'The default warehouse cannot be switched off. Make another warehouse the default first.'), 'tli_ok' => '0'], $back));
            exit;
        }
    }
    $data = ['code' => substr($code, 0, 12), 'name' => $name, 'city' => sanitize_text_field(wp_unslash($_POST['city'] ?? '')), 'country' => strtoupper(substr(sanitize_key($_POST['country'] ?? ''), 0, 2)),
        'address' => sanitize_textarea_field(wp_unslash($_POST['address'] ?? '')), 'dispatch' => sanitize_text_field(wp_unslash($_POST['dispatch'] ?? '')), 'active' => $active];
    if ($id) {
        $wpdb->update($w, $data, ['id' => $id]);
    } else {
        $wpdb->insert($w, $data + ['created_at' => current_time('mysql', true), 'is_default' => 0]);
        $id = (int) $wpdb->insert_id;
    }
    if ($default && $active) {
        $wpdb->query("UPDATE {$w} SET is_default = 0");
        $wpdb->update($w, ['is_default' => 1], ['id' => $id]);
    }
    tli_wh_flush();
    wp_safe_redirect(add_query_arg(['tli_msg' => rawurlencode('Warehouse saved.'), 'tli_ok' => '1'], $back));
    exit;
});

/** Moves stock of one size between two warehouses (total unchanged). */
function tli_wh_transfer(int $pid, int $from, int $to, int $qty, string $note = ''): void
{
    $u = wc_get_product($pid);
    if (!$u || !$u->managing_stock() || $from === $to || $qty <= 0 || !tli_wh($from) || !tli_wh($to)) {
        throw new InvalidArgumentException('Choose a counted size, two different warehouses and a quantity.');
    }
    tli_wh_reconcile($u);
    $have = tli_wh_qty($pid)[$from] ?? 0;
    if ($have < $qty) {
        throw new InvalidArgumentException(sprintf('Only %d in %s — cannot move %d.', $have, tli_wh($from)['name'], $qty));
    }
    $a = tli_wh_change($pid, $from, -$qty);
    $b = tli_wh_change($pid, $to, $qty);
    tli_log($u, -$qty, $a + $qty, $a, 'transfer', trim('To ' . tli_wh($to)['name'] . '. ' . $note), null, $from);
    tli_log($u, $qty, $b - $qty, $b, 'transfer', trim('From ' . tli_wh($from)['name'] . '. ' . $note), null, $to);
}

function tli_page_warehouses(): void
{
    global $wpdb;
    [, $s] = tli_wh_tables();
    $edit = absint($_GET['edit'] ?? 0); // phpcs:ignore WordPress.Security.NonceVerification
    $cur = $edit ? tli_wh($edit) : null;
    tli_head('Warehouses', 'Where stock is kept. Every size has its own count per warehouse; the shop sells the total.', '<a class="tli-btn primary" href="' . esc_url(admin_url('admin.php?page=tl-warehouses&edit=new')) . '">Add warehouse</a>');
    $units = [];
    foreach ($wpdb->get_results("SELECT warehouse_id, SUM(qty) q, COUNT(CASE WHEN qty > 0 THEN 1 END) n FROM {$s} GROUP BY warehouse_id", ARRAY_A) ?: [] as $r) { // phpcs:ignore
        $units[(int) $r['warehouse_id']] = [(int) $r['q'], (int) $r['n']];
    }
    echo '<div class="tli-card flush"><table class="tli-table"><thead><tr><th>Warehouse</th><th>Code</th><th>Location</th><th>Dispatch time</th><th class="num">Pieces</th><th class="num">Sizes in stock</th><th>Status</th><th></th></tr></thead><tbody>';
    foreach (tli_warehouses(false) as $w) {
        [$q, $n] = $units[(int) $w['id']] ?? [0, 0];
        printf('<tr><td><b>%s</b>%s</td><td class="mono">%s</td><td>%s</td><td>%s</td><td class="num">%s</td><td class="num">%d</td><td>%s</td><td class="num"><a class="tli-btn sm ghost" href="%s">Edit</a></td></tr>',
            esc_html($w['name']), (int) $w['is_default'] ? ' ' . tli_badge('Default', 'info') : '', esc_html($w['code']), esc_html(trim($w['city'] . ', ' . $w['country'], ', ')) ?: '<span class="muted">—</span>',
            $w['dispatch'] !== '' ? esc_html($w['dispatch']) : '<span class="muted">Store default</span>', esc_html(number_format_i18n($q)), $n, (int) $w['active'] ? tli_badge('Active', 'ok') : tli_badge('Off', 'neutral'), esc_url(admin_url('admin.php?page=tl-warehouses&edit=' . $w['id'])));
    }
    echo '</tbody></table></div>';
    if ($edit || ($_GET['edit'] ?? '') === 'new') { // phpcs:ignore WordPress.Security.NonceVerification
        $v = $cur ?? ['id' => 0, 'code' => '', 'name' => '', 'city' => '', 'country' => 'IN', 'address' => '', 'dispatch' => '', 'is_default' => 0, 'active' => 1];
        printf('<div class="tli-card"><h2>%s</h2><form method="post" action="%s"><input type="hidden" name="action" value="tli_wh_save"><input type="hidden" name="id" value="%d">%s<div class="tli-po-head">',
            $cur ? 'Edit ' . esc_html($cur['name']) : 'New warehouse', esc_url(admin_url('admin-post.php')), (int) $v['id'], wp_nonce_field('tli_wh_save', '_wpnonce', true, false));
        $f = static fn ($k, $label, $ph = '', $extra = '') => sprintf('<label class="tli-field"><span>%s</span><input class="tli-input" name="%s" value="%s" placeholder="%s"%s></label>', esc_html($label), esc_attr($k), esc_attr((string) $v[$k]), esc_attr($ph), $extra);
        echo $f('name', 'Name', 'e.g. London warehouse', ' required') . $f('code', 'Short code', 'e.g. LON', ' required maxlength="12"') . $f('city', 'City', '') . '</div><div class="tli-po-head">';
        echo $f('country', 'Country code', 'IN, GB, AE…', ' maxlength="2"') . $f('dispatch', 'Dispatch time from here', 'e.g. 2–3 working days') . '</div>';
        printf('<label class="tli-field"><span>Address (printed as "ships from" on packing slips)</span><textarea class="tli-input tli-textarea" name="address" rows="2">%s</textarea></label>', esc_textarea((string) $v['address']));
        printf('<p><label><input type="checkbox" name="active" value="1"%s> Active</label> &nbsp; <label><input type="checkbox" name="is_default" value="1"%s> Default warehouse (orders use it when a product has no warehouse of its own)</label></p>', checked((int) $v['active'], 1, false), checked((int) $v['is_default'], 1, false));
        echo '<p><button class="tli-btn primary">Save warehouse</button></p></form></div>';
    }
    echo '<div class="tli-card"><h2>How it works</h2><ul class="tli-help"><li>Record stock per warehouse with <b>Inventory → Adjust</b> (choose the warehouse), and move stock between warehouses with <b>Move to another warehouse</b>.</li><li>Each product can have its own warehouse and dispatch time (product edit screen → <b>TL Commerce Hub</b> box). Orders take stock from it, else from the default warehouse, else from the warehouse with the most.</li><li>Pick lists and packing slips can be printed per warehouse (Orders → Warehouse printouts).</li><li>Purchase orders are delivered to the warehouse you choose on the order.</li></ul></div>';
    tli_foot();
}

// ---- Product screen: ships-from warehouse and dispatch time (hub), dispatch time (any site)
add_action('woocommerce_product_options_general_product_data', static function (): void {
    global $post;
    echo '<div class="options_group"><p class="form-field"><strong>TL Commerce Hub</strong></p>';
    if (tli_mode() === 'hub' && tli_warehouses()) {
        $opts = ['' => 'Default warehouse'];
        foreach (tli_warehouses() as $w) {
            $opts[(string) $w['id']] = $w['name'];
        }
        woocommerce_wp_select(['id' => '_tl_warehouse', 'label' => 'Ships from', 'options' => $opts, 'value' => (string) get_post_meta($post->ID, '_tl_warehouse', true), 'desc_tip' => true, 'description' => 'Orders take this product from here first.']);
    }
    woocommerce_wp_text_input(['id' => '_tl_dispatch', 'label' => 'Dispatch time', 'placeholder' => 'e.g. 7–10 working days', 'desc_tip' => true, 'description' => 'Shown on the product page and in the bag. Leave empty to use the warehouse\'s or the store\'s dispatch time.']);
    echo '</div>';
});
add_action('woocommerce_admin_process_product_object', static function (WC_Product $p): void {
    // phpcs:disable WordPress.Security.NonceVerification -- WooCommerce verifies the product form nonce
    if (isset($_POST['_tl_warehouse'])) {
        $p->update_meta_data('_tl_warehouse', absint($_POST['_tl_warehouse']) ?: '');
    }
    if (isset($_POST['_tl_dispatch'])) {
        $p->update_meta_data('_tl_dispatch', sanitize_text_field(wp_unslash($_POST['_tl_dispatch'])));
    }
    // phpcs:enable
});

/**
 * Dispatch time a customer sees for a product: the product's own, else its warehouse's, else the store's
 * (TL Commerce Hub → Settings → "Dispatch time"). Empty when none is set.
 */
function tli_dispatch_text(WC_Product $p): string
{
    $parent = $p->get_parent_id() ? wc_get_product($p->get_parent_id()) : null;
    $own = trim((string) ($p->get_meta('_tl_dispatch') ?: ($parent ? $parent->get_meta('_tl_dispatch') : '')));
    if ($own !== '') {
        return $own;
    }
    if (tli_mode() === 'hub' && tli_warehouses() && ($w = tli_wh(tli_wh_for($parent ?: $p))) && $w['dispatch'] !== '') {
        return $w['dispatch'];
    }
    return function_exists('tli_invoice_conf') ? trim((string) tli_invoice_conf()['dispatch']) : '';
}

// Product page: one quiet line under the price / buy box.
add_action('woocommerce_single_product_summary', static function (): void {
    global $product;
    if ($product instanceof WC_Product && ($t = tli_dispatch_text($product)) !== '') {
        printf('<p class="tl-dispatch">%s</p>', esc_html(sprintf(__('Dispatched within %s', 'tl-commerce-hub'), $t)));
    }
}, 31);
// Bag and checkout (classic and block): the line's dispatch time; saved on the order line for the team and emails.
add_filter('woocommerce_get_item_data', static function (array $data, array $item) {
    $p = $item['data'] ?? null;
    if ($p instanceof WC_Product && ($t = tli_dispatch_text($p)) !== '') {
        $data[] = ['key' => __('Dispatch', 'tl-commerce-hub'), 'value' => $t];
    }
    return $data;
}, 10, 2);
add_action('woocommerce_checkout_create_order_line_item', static function ($item, $key, $values): void {
    $p = $values['data'] ?? null;
    if ($p instanceof WC_Product && ($t = tli_dispatch_text($p)) !== '') {
        $item->add_meta_data(__('Dispatch', 'tl-commerce-hub'), $t, true);
    }
}, 10, 3);
add_action('woocommerce_store_api_checkout_update_order_from_request', static function (WC_Order $o): void {
    foreach ($o->get_items() as $item) {
        $p = $item instanceof WC_Order_Item_Product ? $item->get_product() : null;
        if ($p && !$item->get_meta(__('Dispatch', 'tl-commerce-hub')) && ($t = tli_dispatch_text($p)) !== '') {
            $item->add_meta_data(__('Dispatch', 'tl-commerce-hub'), $t, true);
            $item->save_meta_data();
        }
    }
});
