<?php
/** Stock reads and writes. WooCommerce holds the quantity; every change is also written to the movement ledger. */

declare(strict_types=1);

defined('ABSPATH') || exit;

const TLI_REASONS = [
    'received' => ['Stock received', 1],
    'returned' => ['Customer return (back on sale)', 1],
    'found' => ['Found in stocktake', 1],
    'damaged' => ['Damaged / unsellable', -1],
    'lost' => ['Lost or missing', -1],
    'sample' => ['Sample / gift / internal use', -1],
    'count' => ['Stocktake: set exact count', 0],
];

/** Every sellable unit (simple products and size variations) with its stock, grouped by product. */
function tli_inventory_rows(string $search = '', string $filter = 'all'): array
{
    $low = (int) get_option('tli_low_stock', 5);
    $out = [];
    foreach (wc_get_products(['limit' => -1, 'status' => ['publish', 'private', 'draft'], 'orderby' => 'title', 'order' => 'ASC']) as $p) {
        $units = $p->is_type('variable') ? array_filter(array_map('wc_get_product', $p->get_children())) : [$p];
        $rows = [];
        foreach ($units as $u) {
            $managed = $u->managing_stock();
            $qty = $managed ? (int) $u->get_stock_quantity() : null;
            $state = !$managed ? ($u->is_in_stock() ? 'untracked' : 'out') : ($qty <= 0 ? 'out' : ($qty <= $low ? 'low' : 'ok'));
            $rows[] = ['id' => $u->get_id(), 'sku' => $u->get_sku(), 'size' => $u->is_type('variation') ? strtoupper((string) $u->get_attribute('pa_size')) : '—',
                'qty' => $qty, 'state' => $state, 'price' => $u->get_price()];
        }
        $hay = strtolower($p->get_name() . ' ' . $p->get_sku() . ' ' . implode(' ', array_column($rows, 'sku')));
        if ($search !== '' && !str_contains($hay, strtolower($search))) {
            continue;
        }
        if ($filter === 'low' && !array_filter($rows, fn ($r) => $r['state'] === 'low')) {
            continue;
        }
        if ($filter === 'out' && !array_filter($rows, fn ($r) => $r['state'] === 'out')) {
            continue;
        }
        if ($filter === 'untracked' && !array_filter($rows, fn ($r) => $r['state'] === 'untracked')) {
            continue;
        }
        $known = array_filter(array_column($rows, 'qty'), 'is_int');
        $out[] = ['product' => $p, 'rows' => $rows, 'total' => $known ? array_sum($known) : null,
            'cats' => wp_strip_all_tags(wc_get_product_category_list($p->get_id(), ', '))];
    }
    return $out;
}

function tli_stock_summary(): array
{
    $s = ['units' => 0, 'skus' => 0, 'low' => 0, 'out' => 0, 'untracked' => 0, 'value' => 0.0];
    foreach (tli_inventory_rows() as $g) {
        foreach ($g['rows'] as $r) {
            $s['skus']++;
            if ($r['state'] !== 'ok') {
                $s[$r['state']]++;
            }
            if (is_int($r['qty']) && $r['qty'] > 0) {
                $s['units'] += $r['qty'];
                $s['value'] += $r['qty'] * (float) $r['price'];
            }
        }
    }
    return $s;
}

/**
 * Applies an adjustment and records it. Returns [before, after] for the warehouse it was made in (the product's
 * warehouse when none is given; the whole stock when the site has no warehouses, e.g. a storefront).
 */
function tli_adjust(int $id, string $reason, int $qty, string $note = '', ?int $order_id = null, ?int $wh = null): array
{
    $u = wc_get_product($id);
    if (!$u || !isset(TLI_REASONS[$reason])) {
        throw new InvalidArgumentException('Unknown product or reason.');
    }
    if (!$u->managing_stock()) {
        $u->set_manage_stock(true);
        $u->set_stock_quantity(0);
        $u->save();
    }
    $sign = TLI_REASONS[$reason][1];
    $use_wh = function_exists('tli_warehouses') && tli_mode() === 'hub' && tli_warehouses();
    if ($use_wh) {
        tli_wh_reconcile($u);
        $wh = $wh && tli_wh($wh) ? $wh : tli_wh_for($u);
        $before = tli_wh_qty($id)[$wh] ?? 0;
        $after = $sign === 0 ? max(0, $qty) : $before + $sign * abs($qty);
        if ($after < 0) {
            throw new InvalidArgumentException(sprintf('Only %d in %s — cannot remove %d.', $before, tli_wh($wh)['name'], abs($qty)));
        }
        tli_wh_change($id, $wh, $after - $before);
        $total = (int) $u->get_stock_quantity() + ($after - $before);
        tli_without_auto_log(static fn () => wc_update_product_stock($u, $total, 'set'));
        tli_log($u, $after - $before, $before, $after, $reason, $note, $order_id, $wh);
        return [$before, $after];
    }
    $before = (int) $u->get_stock_quantity();
    $after = $sign === 0 ? max(0, $qty) : $before + $sign * abs($qty);
    if ($after < 0) {
        throw new InvalidArgumentException(sprintf('Only %d in stock — cannot remove %d.', $before, abs($qty)));
    }
    tli_without_auto_log(static fn () => wc_update_product_stock($u, $after, 'set'));
    tli_log($u, $after - $before, $before, $after, $reason, $note, $order_id);
    return [$before, $after];
}

/** Ledger write. */
function tli_log(WC_Product $u, int $change, ?int $before, ?int $after, string $reason, string $note = '', ?int $order_id = null, ?int $warehouse_id = null): void
{
    global $wpdb;
    if ($change === 0 && $reason !== 'count') {
        return;
    }
    $wpdb->insert($wpdb->prefix . 'tl_stock_movements', [
        'product_id' => $u->get_id(), 'sku' => (string) $u->get_sku(), 'qty_change' => $change, 'qty_before' => $before, 'qty_after' => $after,
        'reason' => $reason, 'note' => mb_substr($note, 0, 250), 'order_id' => $order_id, 'warehouse_id' => $warehouse_id, 'user_id' => get_current_user_id() ?: null,
        'created_at' => current_time('mysql', true),
    ]);
}

function tli_movements(int $limit = 50, int $product_id = 0): array
{
    global $wpdb;
    $t = $wpdb->prefix . 'tl_stock_movements';
    $sql = $product_id ? $wpdb->prepare("SELECT * FROM {$t} WHERE product_id = %d ORDER BY id DESC LIMIT %d", $product_id, $limit)
        : $wpdb->prepare("SELECT * FROM {$t} ORDER BY id DESC LIMIT %d", $limit);
    return $wpdb->get_results($sql, ARRAY_A) ?: [];
}

// Changes made elsewhere (orders, WooCommerce screens) are logged too, unless TL Commerce Hub made them itself.
function tli_without_auto_log(callable $fn)
{
    $GLOBALS['tli_quiet'] = true;
    try {
        return $fn();
    } finally {
        $GLOBALS['tli_quiet'] = false;
    }
}
add_action('woocommerce_reduce_order_item_stock', static function ($item, $change, $order): void {
    $p = $item->get_product();
    if ($p && is_array($change)) {
        tli_log($p, (int) $change['to'] - (int) $change['from'], (int) $change['from'], (int) $change['to'], 'sold', '', $order->get_id());
    }
}, 10, 3);
add_action('woocommerce_restore_order_item_stock', static function ($item, $new, $old, $order): void {
    $p = $item->get_product();
    if ($p) {
        tli_log($p, (int) $new - (int) $old, (int) $old, (int) $new, 'order_restored', '', $order->get_id());
    }
}, 10, 4);
// Stock typed into WooCommerce's own product/variation screens is logged as an edit (only during those saves,
// so order sales — logged above — are never counted twice).
foreach (['woocommerce_product_set_stock', 'woocommerce_variation_set_stock'] as $hook) {
    add_action($hook, static function (WC_Product $p): void {
        $editing = doing_action('woocommerce_process_product_meta') || doing_action('woocommerce_save_product_variation') || doing_action('wp_ajax_woocommerce_save_variations');
        if (!empty($GLOBALS['tli_quiet']) || !$editing) {
            return;
        }
        $before = (int) get_post_meta($p->get_id(), '_tli_last_seen', true);
        $after = (int) $p->get_stock_quantity();
        if ($before !== $after) {
            tli_log($p, $after - $before, $before, $after, 'edit', 'Changed in the WooCommerce product screen');
        }
    });
}
add_action('woocommerce_before_product_object_save', static function (WC_Product $p): void {
    if ($p->get_id()) {
        update_post_meta($p->get_id(), '_tli_last_seen', (int) get_post_meta($p->get_id(), '_stock', true));
    }
});

function tli_reason_label(string $r): string
{
    return TLI_REASONS[$r][0] ?? ['sold' => 'Sold (order)', 'order_restored' => 'Order cancelled — back in stock', 'edit' => 'Edited in WooCommerce', 'transfer' => 'Moved between warehouses'][$r] ?? ucfirst($r);
}
