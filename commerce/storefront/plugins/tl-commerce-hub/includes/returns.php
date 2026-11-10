<?php
/**
 * Returns and exchanges. A return is recorded against an order (this store's or another store's), received at the
 * warehouse, then either put back on sale (stock goes up, logged) or set aside as damaged; then refunded,
 * exchanged or closed. Refunds themselves are issued in WooCommerce / the payment gateway.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

const TLI_RETURN_STATES = [
    'requested' => ['Requested', 'warn'], 'received' => ['Received — check condition', 'info'], 'restocked' => ['Back on sale', 'ok'],
    'damaged' => ['Received damaged', 'bad'], 'refunded' => ['Refunded', 'neutral'], 'exchanged' => ['Exchanged', 'neutral'], 'closed' => ['Closed', 'neutral'],
];
const TLI_RETURN_REASONS = ['size' => 'Wrong size / fit', 'damaged' => 'Arrived damaged', 'wrong' => 'Wrong item sent', 'changed_mind' => 'Changed mind', 'quality' => 'Quality issue', 'other' => 'Other'];

function tli_returns_table(): string
{
    global $wpdb;
    return $wpdb->prefix . 'tl_returns';
}

function tli_install_returns(): void
{
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta('CREATE TABLE ' . tli_returns_table() . " (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        kind varchar(16) NOT NULL DEFAULT 'return',
        store varchar(100) NOT NULL DEFAULT '',
        order_number varchar(64) NOT NULL,
        customer varchar(150) NOT NULL DEFAULT '',
        lines_json longtext NOT NULL,
        reason varchar(32) NOT NULL,
        status varchar(16) NOT NULL DEFAULT 'requested',
        note varchar(255) NOT NULL DEFAULT '',
        user_id bigint unsigned NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY status (status)
    ) {$wpdb->get_charset_collate()};");
}

add_action('admin_post_tli_return_new', static function (): void {
    global $wpdb;
    if (!current_user_can('tl_returns')) {
        wp_die('Not allowed.', 403);
    }
    check_admin_referer('tli_return_new');
    $lines = [];
    foreach ((array) ($_POST['sku'] ?? []) as $i => $sku) {
        $sku = sanitize_text_field(wp_unslash($sku));
        $qty = absint($_POST['qty'][$i] ?? 0);
        if ($sku !== '' && $qty > 0) {
            $lines[] = ['sku' => $sku, 'qty' => $qty];
        }
    }
    $order = sanitize_text_field(wp_unslash($_POST['order_number'] ?? ''));
    if ($order === '' || !$lines) {
        wp_safe_redirect(add_query_arg(['tli_msg' => rawurlencode('Enter the order number and at least one item (SKU and quantity).'), 'tli_ok' => '0'], admin_url('admin.php?page=tl-returns')));
        exit;
    }
    foreach ($lines as $l) {
        if (!wc_get_product_id_by_sku($l['sku'])) {
            wp_safe_redirect(add_query_arg(['tli_msg' => rawurlencode("SKU {$l['sku']} is not in the catalogue."), 'tli_ok' => '0'], admin_url('admin.php?page=tl-returns')));
            exit;
        }
    }
    $now = current_time('mysql', true);
    $wpdb->insert(tli_returns_table(), ['kind' => sanitize_key($_POST['kind'] ?? 'return') === 'exchange' ? 'exchange' : 'return', 'store' => sanitize_text_field(wp_unslash($_POST['store'] ?? '')),
        'order_number' => $order, 'customer' => sanitize_text_field(wp_unslash($_POST['customer'] ?? '')), 'lines_json' => wp_json_encode($lines),
        'reason' => array_key_exists($_POST['reason'] ?? '', TLI_RETURN_REASONS) ? $_POST['reason'] : 'other', 'note' => sanitize_text_field(wp_unslash($_POST['note'] ?? '')),
        'status' => 'requested', 'user_id' => get_current_user_id(), 'created_at' => $now, 'updated_at' => $now]);
    wp_safe_redirect(add_query_arg(['tli_msg' => rawurlencode('Return recorded.'), 'tli_ok' => '1'], admin_url('admin.php?page=tl-returns')));
    exit;
});

add_action('admin_post_tli_return_step', static function (): void {
    global $wpdb;
    $id = absint($_POST['return'] ?? 0);
    check_admin_referer('tli_return_' . $id);
    $to = sanitize_key($_POST['to'] ?? '');
    $r = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . tli_returns_table() . ' WHERE id = %d', $id), ARRAY_A);
    $allowed = ['requested' => ['received', 'closed'], 'received' => ['restocked', 'damaged'], 'restocked' => ['refunded', 'exchanged', 'closed'], 'damaged' => ['refunded', 'exchanged', 'closed']];
    if (!$r || !in_array($to, $allowed[$r['status']] ?? [], true)) {
        wp_die('That step is not possible now.', 400);
    }
    // Putting items back on sale changes stock, so it needs the stock permission.
    if (!current_user_can($to === 'restocked' ? 'tl_adjust' : 'tl_returns')) {
        wp_die('Not allowed.', 403);
    }
    $msg = 'Return #' . $id . ': ' . TLI_RETURN_STATES[$to][0] . '.';
    if ($to === 'restocked') {
        foreach (json_decode($r['lines_json'], true) ?: [] as $l) {
            $pid = wc_get_product_id_by_sku($l['sku']);
            if ($pid) {
                tli_adjust($pid, 'returned', (int) $l['qty'], 'Return #' . $id . ' · order #' . $r['order_number']);
            }
        }
        $msg .= ' Stock added.';
    }
    $wpdb->update(tli_returns_table(), ['status' => $to, 'updated_at' => current_time('mysql', true)], ['id' => $id]);
    wp_safe_redirect(add_query_arg(['tli_msg' => rawurlencode($msg), 'tli_ok' => '1'], admin_url('admin.php?page=tl-returns')));
    exit;
});

function tli_page_returns(): void
{
    global $wpdb;
    tli_head('Returns & exchanges', 'Record returns, check them in at the warehouse and put good items back on sale.');
    $rows = $wpdb->get_results('SELECT * FROM ' . tli_returns_table() . ' ORDER BY id DESC LIMIT 100', ARRAY_A) ?: [];
    $steps = ['requested' => [['received', 'Mark received'], ['closed', 'Cancel']], 'received' => [['restocked', 'Good — back on sale'], ['damaged', 'Damaged — set aside']],
        'restocked' => [['refunded', 'Refunded'], ['exchanged', 'Exchanged'], ['closed', 'Close']], 'damaged' => [['refunded', 'Refunded'], ['exchanged', 'Exchanged'], ['closed', 'Close']]];
    echo '<div class="tli-card flush"><table class="tli-table"><thead><tr><th>#</th><th>Order</th><th>Type</th><th>Items</th><th>Reason</th><th>Status</th><th>Next</th></tr></thead><tbody>';
    if (!$rows) {
        echo '<tr><td colspan="7"><div class="tli-empty">No returns yet.</div></td></tr>';
    }
    foreach ($rows as $r) {
        [$label, $tone] = TLI_RETURN_STATES[$r['status']] ?? [$r['status'], 'neutral'];
        $items = implode(', ', array_map(static fn ($l) => $l['qty'] . '× ' . $l['sku'], json_decode($r['lines_json'], true) ?: []));
        printf('<tr><td>%d<span class="muted small">%s</span></td><td><b>#%s</b><span class="muted small">%s%s</span></td><td>%s</td><td class="mono">%s</td><td>%s%s</td><td>%s</td><td class="tli-steps">',
            (int) $r['id'], esc_html(wp_date('d M', strtotime($r['created_at'] . ' UTC'))), esc_html($r['order_number']), esc_html($r['store'] ?: get_bloginfo('name')), $r['customer'] ? ' · ' . esc_html($r['customer']) : '',
            esc_html(ucfirst($r['kind'])), esc_html($items), esc_html(TLI_RETURN_REASONS[$r['reason']] ?? $r['reason']), $r['note'] ? '<span class="muted small">' . esc_html($r['note']) . '</span>' : '', tli_badge($label, $tone));
        foreach ($steps[$r['status']] ?? [] as [$to, $text]) {
            if ($to === 'restocked' && !current_user_can('tl_adjust')) {
                continue;
            }
            printf('<form method="post" action="%s"><input type="hidden" name="action" value="tli_return_step"><input type="hidden" name="return" value="%d"><input type="hidden" name="to" value="%s">%s<button class="tli-btn sm%s">%s</button></form>',
                esc_url(admin_url('admin-post.php')), (int) $r['id'], esc_attr($to), wp_nonce_field('tli_return_' . $r['id'], '_wpnonce', true, false), in_array($to, ['received', 'restocked'], true) ? ' primary' : '', esc_html($text));
        }
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';
    $stores = array_merge([get_bloginfo('name')], array_column(tli_mode() === 'hub' ? tli_stores() : [], 'name'));
    echo '<div class="tli-card"><h2>Record a return or exchange</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="tli-return-form">';
    wp_nonce_field('tli_return_new');
    echo '<input type="hidden" name="action" value="tli_return_new"><div class="tli-inline">';
    echo '<label class="tli-field"><span>Store</span><select class="tli-input" name="store">';
    foreach ($stores as $s) {
        printf('<option>%s</option>', esc_html($s));
    }
    echo '</select></label><label class="tli-field"><span>Order number</span><input class="tli-input" name="order_number" required></label><label class="tli-field"><span>Customer</span><input class="tli-input" name="customer"></label>';
    echo '<label class="tli-field"><span>Type</span><select class="tli-input" name="kind"><option value="return">Return</option><option value="exchange">Exchange</option></select></label>';
    echo '<label class="tli-field"><span>Reason</span><select class="tli-input" name="reason">';
    foreach (TLI_RETURN_REASONS as $k => $l) {
        printf('<option value="%s">%s</option>', esc_attr($k), esc_html($l));
    }
    echo '</select></label></div><div class="tli-inline">';
    for ($i = 0; $i < 3; $i++) {
        printf('<label class="tli-field"><span>Item %d SKU</span><input class="tli-input" name="sku[]" placeholder="e.g. MI-001-M"%s></label><label class="tli-field"><span>Qty</span><input class="tli-input" style="min-width:70px;width:70px" type="number" min="1" name="qty[]" value="%s"></label>', $i + 1, $i === 0 ? ' required' : '', $i === 0 ? '1' : '');
    }
    echo '</div><label class="tli-field"><span>Note</span><input class="tli-input" name="note" maxlength="200" style="max-width:520px"></label><p><button class="tli-btn primary">Record return</button></p></form></div>';
    tli_foot();
}
