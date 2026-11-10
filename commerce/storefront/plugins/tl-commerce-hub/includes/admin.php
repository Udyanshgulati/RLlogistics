<?php
/** TL Commerce Hub screens in WordPress admin. */

declare(strict_types=1);

defined('ABSPATH') || exit;

add_action('admin_menu', static function (): void {
    $cap = 'tl_view';
    add_menu_page('TL Commerce Hub', 'TL Commerce Hub', $cap, 'tl-inventory', 'tli_page_overview', 'data:image/svg+xml;base64,' . base64_encode(tli_menu_icon()), 3);
    add_submenu_page('tl-inventory', 'Overview', 'Overview', $cap, 'tl-inventory', 'tli_page_overview');
    add_submenu_page('tl-inventory', 'Inventory', 'Inventory', $cap, 'tl-stock', 'tli_page_inventory');
    add_submenu_page('tl-inventory', 'Orders', 'Orders', $cap, 'tl-orders', 'tli_page_orders');
    add_submenu_page('tl-inventory', 'Returns', 'Returns', 'tl_returns', 'tl-returns', 'tli_page_returns');
    add_submenu_page('tl-inventory', 'Stock movements', 'Stock movements', $cap, 'tl-movements', 'tli_page_movements');
    if (tli_mode() === 'hub') {
        add_submenu_page('tl-inventory', 'Stores', 'Stores', 'tl_manage', 'tl-stores', 'tli_page_stores');
    }
    add_submenu_page('tl-inventory', 'Connection', 'Connection', 'tl_manage', 'tl-connection', 'tli_page_connection');
    add_submenu_page('tl-inventory', 'Settings', 'Settings', 'tl_manage', 'tl-settings', 'tli_page_settings');
});

add_action('admin_enqueue_scripts', static function (string $hook): void {
    if (!str_contains($hook, 'tl-')) {
        return;
    }
    wp_enqueue_style('tli', TLI_URL . 'assets/admin.css', [], TLI_VERSION);
    wp_enqueue_script('tli', TLI_URL . 'assets/admin.js', [], TLI_VERSION, ['in_footer' => true]);
});

function tli_menu_icon(): string
{
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M2 3h16v3h-6.5v11h-3V6H2z"/></svg>';
}

function tli_money($v): string
{
    return $v === '' || $v === null ? '—' : wp_strip_all_tags(wc_price((float) $v, ['decimals' => 0]));
}

function tli_badge(string $label, string $tone): string
{
    return sprintf('<span class="tli-badge tone-%s">%s</span>', esc_attr($tone), esc_html($label));
}

function tli_state_badge(string $state): string
{
    return ['ok' => tli_badge('In stock', 'ok'), 'low' => tli_badge('Low stock', 'warn'), 'out' => tli_badge('Out of stock', 'bad'), 'untracked' => tli_badge('Not counted yet', 'neutral')][$state];
}

function tli_head(string $title, string $sub, string $actions = ''): void
{
    echo '<div class="tli-wrap"><div class="tli-top"><div class="tli-brandline"><span class="tli-mark">TL</span><span>TL Commerce Hub <em>by Thoda Logic</em></span><span class="tli-mode">' . (tli_mode() === 'hub' ? 'Hub · central stock' : 'Storefront · sells from hub stock') . '</span></div></div>';
    echo '<div class="tli-page-head"><div><h1>' . esc_html($title) . '</h1><p>' . esc_html($sub) . '</p></div><div class="tli-actions">' . $actions . '</div></div>';
    if (!empty($_GET['tli_msg'])) { // phpcs:ignore WordPress.Security.NonceVerification
        $ok = ($_GET['tli_ok'] ?? '1') === '1'; // phpcs:ignore WordPress.Security.NonceVerification
        echo '<div class="tli-flash ' . ($ok ? 'is-ok' : 'is-bad') . '">' . esc_html(wp_unslash($_GET['tli_msg'])) . '</div>'; // phpcs:ignore
    }
}

function tli_foot(): void
{
    echo '</div>';
}

/* ── Overview ─────────────────────────────────────────────────────────── */
function tli_page_overview(): void
{
    $range = sanitize_key($_GET['range'] ?? '7d'); // phpcs:ignore WordPress.Security.NonceVerification
    $days = ['today' => 0, '7d' => 7, '30d' => 30][$range] ?? 7;
    $from = $days === 0 ? wp_date('Y-m-d 00:00:00') : wp_date('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
    $count = static fn (array $status, ?string $after = null): int => count(wc_get_orders(['status' => $status, 'limit' => -1, 'return' => 'ids'] + ($after ? ['date_created' => '>=' . strtotime($after)] : [])));
    global $wpdb;
    $hub = tli_mode() === 'hub';
    $rt = $wpdb->prefix . 'tl_remote_orders';
    $rcount = static fn (array $st, ?string $after = null): int => $hub ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$rt} WHERE status IN ('" . implode("','", array_map('esc_sql', $st)) . "')" . ($after ? $wpdb->prepare(' AND placed_at >= %s', get_gmt_from_date($after)) : '')) : 0;
    $paid = ['processing', 'tl-packed', 'tl-dispatched', 'completed', 'on-hold'];
    $new = $count($paid, $from) + $rcount($paid, $from);
    $topack = $count(['processing']) + $rcount(['processing']);
    $packed = $count(['tl-packed']) + $rcount(['tl-packed']);
    $transit = $count(['tl-dispatched']) + $rcount(['tl-dispatched']);
    $sales = 0.0;
    foreach (wc_get_orders(['status' => ['processing', 'tl-packed', 'tl-dispatched', 'completed'], 'limit' => -1, 'date_created' => '>=' . strtotime($from)]) as $o) {
        $sales += (float) $o->get_total();
    }
    if ($hub) {
        $sales += (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(total),0) FROM {$rt} WHERE currency = 'INR' AND status IN ('processing','tl-packed','tl-dispatched','completed') AND placed_at >= %s", get_gmt_from_date($from)));
    }
    $s = tli_stock_summary();
    $seg = '';
    foreach (['today' => 'Today', '7d' => 'Last 7 days', '30d' => 'Last 30 days'] as $k => $l) {
        $seg .= sprintf('<a class="%s" href="%s">%s</a>', $range === $k ? 'on' : '', esc_url(admin_url('admin.php?page=tl-inventory&range=' . $k)), $l);
    }
    tli_head('Overview', ($hub ? 'All stores' : get_bloginfo('name')) . ' · Delhi warehouse', '<nav class="tli-seg">' . $seg . '</nav>');
    $k = static fn ($label, $value, $hint, $href, $tone = '') => printf('<a class="tli-kpi %s" href="%s"><span class="k-label">%s</span><span class="k-value">%s</span><span class="k-hint">%s</span></a>', esc_attr($tone), esc_url($href), esc_html($label), esc_html((string) $value), esc_html($hint));
    echo '<div class="tli-kpis">';
    $k('New orders', $new, ['today' => 'Placed today', '7d' => 'Last 7 days', '30d' => 'Last 30 days'][$range] ?? '', admin_url('admin.php?page=tl-orders'));
    $k('Sales', tli_money($sales), $hub ? 'Paid orders, all stores (INR)' : 'Paid orders in the period', admin_url('admin.php?page=tl-orders'));
    $k('To pack', $topack, 'Paid, waiting to be packed', admin_url('admin.php?page=tl-orders&stage=processing'), $topack ? 'is-warn' : '');
    $k('Ready to dispatch', $packed, 'Packed at the warehouse', admin_url('admin.php?page=tl-orders&stage=tl-packed'));
    $k('In transit', $transit, 'With the courier', admin_url('admin.php?page=tl-orders&stage=tl-dispatched'));
    $k('Low stock', $s['low'], 'Sizes at or below the reorder level', admin_url('admin.php?page=tl-stock&filter=low'), $s['low'] ? 'is-warn' : '');
    $k('Out of stock', $s['out'], 'Sizes with nothing to sell', admin_url('admin.php?page=tl-stock&filter=out'), $s['out'] ? 'is-bad' : '');
    echo '</div>';

    echo '<section class="tli-card tli-route"><div class="tli-card-head"><div><h2>Shipment journey</h2><p>Orders by stage. Counts change as the team marks orders packed, dispatched and delivered.</p></div></div>';
    $delivered = $count(['completed']) + $rcount(['completed']);
    printf('<ol class="tli-stops"><li class="%s"><b>%d</b><span>To pack</span></li><li class="%s"><b>%d</b><span>Packed · Delhi</span></li><li class="%s"><b>%d</b><span>With courier</span></li><li class="%s"><b>%d</b><span>Delivered (all time)</span></li></ol></section>',
        $topack ? 'done' : '', $topack, $packed ? 'done' : '', $packed, $transit ? 'live' : '', $transit, $delivered ? 'done' : '', $delivered);

    echo '<div class="tli-grid-2"><div class="tli-stack">';
    // Needs attention
    $att = [];
    if ($topack) {
        $att[] = [$topack, 'paid orders are waiting to be packed', admin_url('admin.php?page=tl-orders&stage=processing'), 'View orders', 'warn'];
    }
    if ($packed) {
        $att[] = [$packed, 'orders are packed and waiting for dispatch', admin_url('admin.php?page=tl-orders&stage=tl-packed'), 'Dispatch orders', 'info'];
    }
    if ($s['out']) {
        $att[] = [$s['out'], 'sizes are out of stock', admin_url('admin.php?page=tl-stock&filter=out'), 'Restock', 'bad'];
    }
    if ($s['low']) {
        $att[] = [$s['low'], 'sizes are at or below the reorder level', admin_url('admin.php?page=tl-stock&filter=low'), 'Restock', 'warn'];
    }
    if (function_exists('schermo_mail_ready') && !schermo_mail_ready()) {
        $att[] = ['!', 'emails are not being sent (no mail service set) — orders, alerts and password resets', admin_url('admin.php?page=tl-settings'), 'How to fix', 'bad'];
    } elseif ($me = get_option('schermo_mail_error')) {
        $att[] = ['!', 'the last email failed: ' . mb_strimwidth((string) $me['error'], 0, 90, '…'), admin_url('admin.php?page=tl-settings'), 'Details', 'bad'];
    }
    $bk = tli_last_backup();
    if (is_dir('/backups') && ($bk === null || time() - $bk > 2 * DAY_IN_SECONDS)) {
        $att[] = ['!', $bk === null ? 'no backup has been made yet' : 'the last backup is more than 2 days old', admin_url('admin.php?page=tl-settings'), 'Details', 'bad'];
    }
    if ($hub) {
        [$pot] = tli_po_tables();
        $late = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$pot} WHERE status IN ('ordered','partial') AND expected_date IS NOT NULL AND expected_date < '" . esc_sql(wp_date('Y-m-d')) . "'");
        if ($late) {
            $att[] = [$late, 'supplier deliveries are overdue', admin_url('admin.php?page=tl-purchasing'), 'Purchasing', 'warn'];
        }
    }
    if ($s['untracked']) {
        $att[] = [$s['untracked'], 'sizes have no stock count yet (sold without a limit)', admin_url('admin.php?page=tl-stock&filter=untracked'), 'Count stock', 'neutral'];
    }
    echo '<section class="tli-card"><div class="tli-card-head"><div><h2>Needs attention</h2><p>Each item links to where it is fixed.</p></div></div>';
    if (!$att) {
        echo '<div class="tli-clear">✓ Nothing needs attention right now.</div>';
    } else {
        echo '<ul class="tli-att">';
        foreach ($att as [$n, $msg, $href, $label, $tone]) {
            printf('<li class="tone-%s"><span class="n">%s</span><span class="msg">%s</span><a class="tli-btn sm" href="%s">%s</a></li>', esc_attr($tone), $n, esc_html($msg), esc_url($href), esc_html($label));
        }
        echo '</ul>';
    }
    echo '</section>';
    // Recent orders
    echo '<section class="tli-card"><div class="tli-card-head"><h2>Recent orders</h2><a class="tli-btn sm ghost" href="' . esc_url(admin_url('admin.php?page=tl-orders')) . '">View all</a></div>';
    tli_orders_table(wc_get_orders(['limit' => 8, 'orderby' => 'date', 'order' => 'DESC']), true);
    echo '</section>';
    if ($hub && ($remote = tli_remote_orders('', 6))) {
        echo '<section class="tli-card flush"><div class="tli-card-head" style="padding:16px 18px 0"><h2>Recent orders from other stores</h2></div>';
        tli_remote_table($remote);
        echo '</section>';
    }
    echo '</div><div class="tli-stack">';
    if (current_user_can('tl_manage') && array_filter(tli_launch_items(), static fn ($i) => !$i[1])) {
        tli_launch_card(true);
    }
    if ($hub) {
        echo '<section class="tli-card"><div class="tli-card-head"><div><h2>Stores</h2><p>Connection health of every storefront.</p></div><a class="tli-btn sm ghost" href="' . esc_url(admin_url('admin.php?page=tl-stores')) . '">Manage</a></div><ul class="tli-moves">';
        printf('<li><div><b>%s</b><span class="small muted">This site · hub</span></div>%s</li>', esc_html(get_bloginfo('name')), tli_badge('Hub', 'info'));
        foreach (tli_stores() as $st) {
            $seen = $st['last_seen'] ? strtotime($st['last_seen'] . ' UTC') : 0;
            $b = !(int) $st['active'] ? tli_badge('Paused', 'neutral') : (!$seen ? tli_badge('Not connected yet', 'warn') : ($st['last_error'] !== '' ? tli_badge('Needs attention', 'bad') : tli_badge('Connected', 'ok')));
            printf('<li><div><b>%s</b><span class="small muted">%s</span></div>%s</li>', esc_html($st['name']), $seen ? esc_html('Last contact ' . human_time_diff($seen) . ' ago') : 'No contact yet', $b);
        }
        if (!tli_stores()) {
            echo '<li><span class="small muted">No other stores connected yet. Add one on the Stores page.</span></li>';
        }
        echo '</ul></section>';
    }
    if ($hub) {
        $inc = tli_incoming_map();
        [$pot, $plt] = tli_po_tables();
        $next = $wpdb->get_results("SELECT p.id, p.supplier, p.expected_date, SUM(GREATEST(CAST(l.qty_ordered AS SIGNED) - l.qty_received - l.qty_damaged, 0)) AS q FROM {$pot} p JOIN {$plt} l ON l.po_id = p.id WHERE p.status IN ('ordered','partial') GROUP BY p.id ORDER BY p.expected_date IS NULL, p.expected_date ASC LIMIT 4", ARRAY_A) ?: [];
        printf('<section class="tli-card"><div class="tli-card-head"><div><h2>Incoming stock</h2><p>%s units on order from suppliers.</p></div><a class="tli-btn sm ghost" href="%s">Purchasing</a></div><ul class="tli-moves">', esc_html(number_format_i18n(array_sum($inc))), esc_url(admin_url('admin.php?page=tl-purchasing')));
        foreach ($next as $n) {
            printf('<li><div><a class="tli-link" href="%s"><b>PO #%d · %s</b></a><span class="small muted">%s</span></div><b>%d</b></li>', esc_url(admin_url('admin.php?page=tl-purchasing&po=' . $n['id'])), (int) $n['id'], esc_html($n['supplier']),
                $n['expected_date'] ? esc_html('Expected ' . wp_date('d M Y', strtotime($n['expected_date']))) : 'No date given', (int) $n['q']);
        }
        if (!$next) {
            echo '<li><span class="small muted">Nothing on order. Create a purchase order when you buy stock from a supplier.</span></li>';
        }
        echo '</ul></section>';
    }
    // Stock at a glance
    printf('<section class="tli-card"><div class="tli-card-head"><div><h2>Stock at a glance</h2><p>Counted units only.</p></div></div><div class="tli-flow"><div><b>%s</b><span>Units in stock</span></div><div><b>%d</b><span>Sizes / SKUs</span></div><div><b>%s</b><span>Stock value at selling price</span></div></div></section>',
        esc_html(number_format_i18n($s['units'])), $s['skus'], esc_html(tli_money($s['value'])));
    // Recent movements
    echo '<section class="tli-card"><div class="tli-card-head"><h2>Recent stock movements</h2><a class="tli-btn sm ghost" href="' . esc_url(admin_url('admin.php?page=tl-movements')) . '">All movements</a></div>';
    tli_movements_list(tli_movements(8));
    echo '</section></div></div>';
    tli_foot();
}

/* ── Inventory ────────────────────────────────────────────────────────── */
function tli_page_inventory(): void
{
    $q = sanitize_text_field(wp_unslash($_GET['s'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification
    $filter = sanitize_key($_GET['filter'] ?? 'all'); // phpcs:ignore WordPress.Security.NonceVerification
    $groups = tli_inventory_rows($q, $filter);
    $incoming = tli_incoming_map();
    $base = admin_url('admin.php?page=tl-stock');
    tli_head('Inventory', 'Stock for every product and size, held at the Delhi warehouse.',
        '<a class="tli-btn" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=tli_export'), 'tli_export')) . '">Export CSV</a><a class="tli-btn primary" href="' . esc_url(admin_url('post-new.php?post_type=product')) . '">Add product</a>');
    if (tli_mode() === 'store') {
        echo '<div class="tli-flash is-ok">This storefront sells from the hub\'s central stock. Numbers here are synced from the hub every 5 minutes; record stock changes at the hub.</div>';
    }
    echo '<div class="tli-legend"><div><b>In stock</b><span>Counted and available to sell</span></div><div><b>Low stock</b><span>At or below ' . (int) get_option('tli_low_stock', 5) . ' (change in Settings)</span></div><div><b>Out of stock</b><span>Nothing left to sell</span></div><div><b>Not counted yet</b><span>Sold without a limit until you record a count</span></div></div>';
    echo '<form class="tli-filters" method="get"><input type="hidden" name="page" value="tl-stock"><nav class="tli-seg">';
    foreach (['all' => 'All', 'low' => 'Low stock', 'out' => 'Out of stock', 'untracked' => 'Not counted'] as $k => $l) {
        printf('<a class="%s" href="%s">%s</a>', $filter === $k ? 'on' : '', esc_url(add_query_arg(['filter' => $k, 's' => $q], $base)), esc_html($l));
    }
    printf('</nav><input type="hidden" name="filter" value="%s"><input class="tli-input" type="search" name="s" value="%s" placeholder="Search product name or SKU"><button class="tli-btn">Search</button></form>', esc_attr($filter), esc_attr($q));
    echo '<div class="tli-card flush"><table class="tli-table"><thead><tr><th></th><th>Product</th><th>Category</th><th class="num">Sizes</th><th class="num">In stock</th><th>Status</th><th></th></tr></thead>';
    if (!$groups) {
        echo '<tbody><tr><td colspan="7"><div class="tli-empty">No products match.</div></td></tr></tbody>';
    }
    foreach ($groups as $i => $g) {
        $p = $g['product'];
        $states = array_column($g['rows'], 'state');
        $worst = in_array('out', $states, true) ? 'out' : (in_array('low', $states, true) ? 'low' : (in_array('untracked', $states, true) ? 'untracked' : 'ok'));
        $img = $p->get_image_id() ? wp_get_attachment_image($p->get_image_id(), [44, 55], false, ['class' => 'tli-thumb']) : '<span class="tli-thumb"></span>';
        printf('<tbody class="tli-group%s" data-group><tr class="tli-prow"><td><button type="button" class="tli-toggle" data-toggle aria-expanded="%s" aria-label="Show sizes">▸</button></td><td><div class="tli-prod">%s<div><a class="tli-link" href="%s">%s</a><span class="mono">%s</span></div></div></td><td class="muted">%s</td><td class="num">%d</td><td class="num"><b>%s</b></td><td>%s</td><td class="num">' . (current_user_can('edit_products') ? '<a class="tli-btn sm ghost" href="%s">Edit product</a>' : '<!-- %s -->') . '</td></tr>',
            $i === 0 && $q !== '' ? ' open' : '', $i === 0 && $q !== '' ? 'true' : 'false', $img, esc_url((string) get_edit_post_link($p->get_id())), esc_html($p->get_name()), esc_html($p->get_sku()),
            esc_html($g['cats']), count($g['rows']), $g['total'] === null ? '—' : esc_html(number_format_i18n($g['total'])), tli_state_badge($worst), esc_url((string) get_edit_post_link($p->get_id())));
        foreach ($g['rows'] as $r) {
            printf('<tr class="tli-vrow"><td></td><td><span class="tli-size">%s</span> <span class="mono">%s</span></td><td></td><td></td><td class="num"><b>%s</b>%s</td><td>%s</td><td class="num"><button type="button" class="tli-btn sm" ' . (tli_mode() === 'store' || !current_user_can('tl_adjust') ? 'disabled title="Not available here"' : '') . ' data-adjust data-id="%d" data-name="%s" data-qty="%s">Adjust</button></td></tr>',
                esc_html($r['size']), esc_html($r['sku']), $r['qty'] === null ? '—' : (int) $r['qty'], !empty($incoming[$r['sku']]) ? ' <a class="tli-incoming" href="' . esc_url(admin_url('admin.php?page=tl-purchasing')) . '" title="On order from suppliers">+' . (int) $incoming[$r['sku']] . ' incoming</a>' : '', tli_state_badge($r['state']), $r['id'], esc_attr($p->get_name() . ($r['size'] !== '—' ? ' · ' . $r['size'] : '')), $r['qty'] === null ? '' : (int) $r['qty']);
        }
        echo '</tbody>';
    }
    echo '</table></div>';
    // Adjust stock side panel
    echo '<dialog class="tli-panel" id="tli-adjust"><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('tli_adjust');
    echo '<input type="hidden" name="action" value="tli_adjust"><input type="hidden" name="id" data-f="id"><input type="hidden" name="back" value="' . esc_attr(remove_query_arg(['tli_msg', 'tli_ok'])) . '">';
    echo '<div class="tli-panel-head"><div><h2>Adjust stock</h2><p data-f="name"></p></div><button type="button" class="tli-x" data-close aria-label="Close">×</button></div><div class="tli-panel-body">';
    echo '<fieldset><legend>What happened?</legend>';
    foreach (TLI_REASONS as $key => [$label, $sign]) {
        printf('<label class="tli-radio"><input type="radio" name="reason" value="%s" data-sign="%d"%s> %s</label>', esc_attr($key), $sign, $key === 'received' ? ' checked' : '', esc_html($label));
    }
    echo '</fieldset><label class="tli-field"><span data-qlabel>Quantity</span><input class="tli-input" type="number" name="qty" min="0" step="1" required></label>';
    echo '<label class="tli-field"><span>Note (optional)</span><input class="tli-input" type="text" name="note" maxlength="200" placeholder="e.g. Delivery from supplier, invoice 1042"></label>';
    echo '<div class="tli-preview" data-preview>On hand <b data-f="before">—</b> → <b data-f="after">—</b></div>';
    echo '</div><div class="tli-panel-foot"><button type="button" class="tli-btn" data-close>Cancel</button><button class="tli-btn primary">Confirm change</button></div></form></dialog>';
    tli_foot();
}

add_action('admin_post_tli_adjust', static function (): void {
    if (!current_user_can('tl_adjust')) {
        wp_die('Not allowed.', 403);
    }
    check_admin_referer('tli_adjust');
    $back = wp_validate_redirect(esc_url_raw(wp_unslash($_POST['back'] ?? '')), admin_url('admin.php?page=tl-stock'));
    try {
        [$before, $after] = tli_adjust(absint($_POST['id'] ?? 0), sanitize_key($_POST['reason'] ?? ''), (int) ($_POST['qty'] ?? 0), sanitize_text_field(wp_unslash($_POST['note'] ?? '')));
        $msg = sprintf('Saved. On hand %d → %d.', $before, $after);
        $ok = '1';
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        $ok = '0';
    }
    wp_safe_redirect(add_query_arg(['tli_msg' => rawurlencode($msg), 'tli_ok' => $ok], $back));
    exit;
});

add_action('admin_post_tli_export', static function (): void {
    if (!current_user_can('tl_view')) {
        wp_die('Not allowed.', 403);
    }
    check_admin_referer('tli_export');
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tl-inventory-' . wp_date('Y-m-d') . '.csv"');
    $f = fopen('php://output', 'w');
    fputcsv($f, ['Product', 'SKU', 'Size', 'In stock', 'Status', 'Price (INR)']);
    foreach (tli_inventory_rows() as $g) {
        foreach ($g['rows'] as $r) {
            fputcsv($f, [$g['product']->get_name(), $r['sku'], $r['size'], $r['qty'] ?? '', $r['state'], $r['price']]);
        }
    }
    exit;
});

/* ── Orders ───────────────────────────────────────────────────────────── */
function tli_orders_table(array $orders, bool $compact = false): void
{
    if (!$orders) {
        echo '<div class="tli-empty"><h3>No orders yet</h3><p>Orders appear here as soon as customers place them.</p></div>';
        return;
    }
    echo '<table class="tli-table' . ($compact ? ' compact' : '') . '"><thead><tr>' . ($compact ? '' : '<th class="tli-chk"><input type="checkbox" data-check-all aria-label="Select all"></th>') . '<th>Order</th><th>Customer</th>' . ($compact ? '' : '<th>Items</th>') . '<th>Ship to</th><th>Stage</th><th class="num">Total</th>' . ($compact ? '' : '<th>Next step</th>') . '</tr></thead><tbody>';
    foreach ($orders as $o) {
        if (!$o instanceof WC_Order) {
            continue;
        }
        [$label, $tone] = tli_stage($o);
        $items = implode(', ', array_map(static fn ($i) => $i->get_quantity() . '× ' . $i->get_name(), array_values($o->get_items())));
        if (!$compact) {
            printf('<tr><td class="tli-chk"><input type="checkbox" form="tli-print-form" name="o[]" value="L:%d" aria-label="Select order"></td>', $o->get_id());
        } else {
            echo '<tr>';
        }
        $inv = $compact || (!$o->is_paid() && !$o->get_meta('_tl_invoice_no')) ? '' : '<a class="tli-mini" target="_blank" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=tli_print&what=invoice&o[]=L:' . $o->get_id()), 'tli_print')) . '">Invoice</a>';
        if (!$compact && ($o->get_meta('_tl_cn_cancel_no') || array_filter($o->get_refunds(), static fn ($r) => $r->get_meta('_tl_cn_no')))) {
            $inv .= '<a class="tli-mini" target="_blank" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=tli_print&what=creditnote&o[]=L:' . $o->get_id()), 'tli_print')) . '">Credit note</a>';
        }
        printf('<td class="nowrap"><a class="tli-link" href="%s">#%s</a><span class="muted small">%s</span>%s</td><td>%s</td>%s<td class="mono">%s</td><td>%s</td><td class="num">%s</td>',
            esc_url(current_user_can('edit_shop_orders') ? $o->get_edit_order_url() : '#'), esc_html($o->get_order_number()), esc_html($o->get_date_created() ? wp_date('d M, H:i', $o->get_date_created()->getTimestamp()) : ''), $inv,
            esc_html(trim($o->get_formatted_billing_full_name()) ?: '—'), $compact ? '' : '<td class="small">' . esc_html(mb_strimwidth($items, 0, 80, '…')) . '</td>',
            esc_html($o->get_shipping_country() ?: $o->get_billing_country() ?: '—'), tli_badge($label, $tone) . tli_tracking_line(...array_values(tli_tracking($o))), esc_html(wp_strip_all_tags($o->get_formatted_order_total())));
        if (!$compact) {
            echo '<td>';
            if (isset(TLI_NEXT[$o->get_status()]) && current_user_can('tl_fulfil')) {
                printf('<form method="post" action="%s" class="tli-next"><input type="hidden" name="action" value="tli_next_stage"><input type="hidden" name="order_id" value="%d">%s%s<button class="tli-btn sm primary">%s</button></form>',
                    esc_url(admin_url('admin-post.php')), $o->get_id(), wp_nonce_field('tli_next_' . $o->get_id(), '_wpnonce', true, false), $o->get_status() === 'tl-packed' ? tli_dispatch_fields() : '', esc_html(TLI_NEXT[$o->get_status()][1]));
            } else {
                echo '<span class="muted small">—</span>';
            }
            echo '</td>';
        }
        echo '</tr>';
        if (!$compact) {
            $steps = ['processing' => 'Paid', 'tl-packed' => 'Packed', 'tl-dispatched' => 'Dispatched', 'completed' => 'Delivered'];
            $at = array_search($o->get_status(), array_keys($steps), true);
            if ($at !== false) {
                echo '<tr class="tli-progress-row"><td colspan="8"><ol class="tli-progress">';
                $n = 0;
                foreach ($steps as $label2) {
                    printf('<li class="%s">%s</li>', $n <= $at ? 'done' : '', esc_html($label2));
                    $n++;
                }
                echo '</ol></td></tr>';
            }
        }
    }
    echo '</tbody></table>';
}

function tli_remote_table(array $rows): void
{
    if (!$rows) {
        return;
    }
    $names = array_column(tli_stores(), 'name', 'id');
    echo '<table class="tli-table"><thead><tr><th class="tli-chk"><input type="checkbox" data-check-all aria-label="Select all"></th><th>Order</th><th>Store</th><th>Customer</th><th>Items</th><th>Ship to</th><th>Stage</th><th class="num">Total</th><th>Next step</th></tr></thead><tbody>';
    foreach ($rows as $o) {
        [$label, $tone] = TLI_STAGES[$o['status']] ?? [ucfirst($o['status']), 'neutral'];
        $items = implode(', ', array_map(static fn ($l) => $l['qty'] . '× ' . ($l['name'] ?: $l['sku']), json_decode($o['lines_json'], true) ?: []));
        printf('<tr><td class="tli-chk"><input type="checkbox" form="tli-print-form" name="o[]" value="R:%d" aria-label="Select order"></td><td class="nowrap"><b>#%s</b><span class="muted small">%s</span></td><td><span class="tli-store-chip">%s</span></td><td>%s</td><td class="small">%s</td><td class="mono">%s</td><td>%s</td><td class="num">%s %s</td><td>',
            (int) $o['id'], esc_html($o['number']), esc_html(wp_date('d M, H:i', strtotime($o['placed_at'] . ' UTC'))), esc_html($names[$o['store_id']] ?? $o['store_id']), esc_html($o['customer'] ?: '—'),
            esc_html(mb_strimwidth($items, 0, 80, '…')), esc_html($o['country'] ?: '—'), tli_badge($label, $tone) . tli_tracking_line((string) ($o['courier'] ?? ''), (string) ($o['awb'] ?? ''), (string) ($o['track_url'] ?? '')), esc_html($o['currency']), esc_html(number_format_i18n((float) $o['total'])));
        if (isset(TLI_NEXT[$o['status']]) && current_user_can('tl_fulfil')) {
            printf('<form method="post" action="%s" class="tli-next"><input type="hidden" name="action" value="tli_remote_next"><input type="hidden" name="remote" value="%d">%s%s<button class="tli-btn sm primary">%s</button></form>',
                esc_url(admin_url('admin-post.php')), (int) $o['id'], wp_nonce_field('tli_remote_' . $o['id'], '_wpnonce', true, false), $o['status'] === 'tl-packed' ? tli_dispatch_fields() : '', esc_html(TLI_NEXT[$o['status']][1]));
        } else {
            echo '<span class="muted small">—</span>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table>';
}

function tli_page_orders(): void
{
    $stage = sanitize_key($_GET['stage'] ?? ''); // phpcs:ignore WordPress.Security.NonceVerification
    $args = ['limit' => 50, 'orderby' => 'date', 'order' => 'DESC'];
    if ($stage !== '' && isset(TLI_STAGES[$stage])) {
        $args['status'] = [$stage];
    }
    tli_head('Orders', tli_mode() === 'hub' ? 'Orders from every store, their stage and the next step.' : 'Every order, its stage and the next step.', '<a class="tli-btn" href="' . esc_url(admin_url('admin.php?page=wc-orders')) . '">WooCommerce order list</a>');
    echo '<nav class="tli-seg mb">';
    foreach (['' => 'All', 'processing' => 'To pack', 'tl-packed' => 'Packed', 'tl-dispatched' => 'Dispatched', 'completed' => 'Delivered', 'pending' => 'Awaiting payment', 'cancelled' => 'Cancelled'] as $k => $l) {
        printf('<a class="%s" href="%s">%s</a>', $stage === $k ? 'on' : '', esc_url(admin_url('admin.php?page=tl-orders' . ($k ? '&stage=' . $k : ''))), esc_html($l));
    }
    echo '</nav>';
    // Printing: tick orders below (checkboxes belong to this form), or print everything waiting to be packed.
    printf('<form id="tli-print-form" class="tli-printbar" method="get" action="%s" target="_blank"><input type="hidden" name="action" value="tli_print">%s<span class="muted">Warehouse printouts:</span><button class="tli-btn sm" name="what" value="pick">Pick list · ticked orders</button><button class="tli-btn sm" name="what" value="slips">Packing slips · ticked orders</button><button class="tli-btn sm" name="what" value="invoice">GST invoices · ticked orders</button><span class="tli-sep"></span><a class="tli-btn sm primary" target="_blank" href="%s">Pick list · all to pack</a><a class="tli-btn sm" target="_blank" href="%s">Packing slips · all to pack</a></form>',
        esc_url(admin_url('admin-post.php')), wp_nonce_field('tli_print', '_wpnonce', false, false),
        esc_url(wp_nonce_url(admin_url('admin-post.php?action=tli_print&what=pick&all_to_pack=1'), 'tli_print')), esc_url(wp_nonce_url(admin_url('admin-post.php?action=tli_print&what=slips&all_to_pack=1'), 'tli_print')));
    echo '<h2 class="tli-sec">' . esc_html(get_bloginfo('name')) . '</h2><div class="tli-card flush">';
    tli_orders_table(wc_get_orders($args));
    echo '</div>';
    if (tli_mode() === 'hub') {
        $remote = tli_remote_orders($stage);
        echo '<h2 class="tli-sec">Other stores</h2><div class="tli-card flush">';
        if ($remote) {
            tli_remote_table($remote);
        } else {
            echo '<div class="tli-empty"><p>' . (tli_stores() ? 'No orders from other stores' . ($stage ? ' at this stage' : ' yet') . '.' : 'No other stores connected yet. Connect one on the Stores page.') . '</p></div>';
        }
        echo '</div>';
    }
    tli_foot();
}

/* ── Movements ────────────────────────────────────────────────────────── */
function tli_movements_list(array $rows): void
{
    if (!$rows) {
        echo '<div class="tli-empty"><p>No stock changes recorded yet. Changes from Inventory, orders from every store and the product screens appear here.</p></div>';
        return;
    }
    echo '<ul class="tli-moves">';
    foreach ($rows as $m) {
        $u = $m['user_id'] ? get_userdata((int) $m['user_id']) : null;
        printf('<li><div><span class="mono">%s</span><span class="small muted">%s · %s%s</span></div><span class="tli-delta %s">%s%d</span></li>',
            esc_html($m['sku'] ?: '#' . $m['product_id']), esc_html(tli_reason_label($m['reason'])), esc_html(wp_date('d M Y, H:i', strtotime($m['created_at'] . ' UTC'))),
            $u ? ' · ' . esc_html($u->display_name) : '', $m['qty_change'] >= 0 ? 'up' : 'down', $m['qty_change'] >= 0 ? '+' : '', (int) $m['qty_change']);
    }
    echo '</ul>';
}

function tli_page_movements(): void
{
    tli_head('Stock movements', 'Every change to stock: who, when, why. Records cannot be edited or deleted.');
    $rows = tli_movements(200);
    echo '<div class="tli-card flush"><table class="tli-table"><thead><tr><th>When</th><th>SKU</th><th>What happened</th><th class="num">Change</th><th class="num">Before → after</th><th>Order</th><th>By</th><th>Note</th></tr></thead><tbody>';
    if (!$rows) {
        echo '<tr><td colspan="8"><div class="tli-empty">No stock changes recorded yet.</div></td></tr>';
    }
    foreach ($rows as $m) {
        $u = $m['user_id'] ? get_userdata((int) $m['user_id']) : null;
        printf('<tr><td class="nowrap small">%s</td><td class="mono">%s</td><td>%s</td><td class="num"><span class="tli-delta %s">%s%d</span></td><td class="num">%s → %s</td><td>%s</td><td>%s</td><td class="small">%s</td></tr>',
            esc_html(wp_date('d M Y, H:i', strtotime($m['created_at'] . ' UTC'))), esc_html($m['sku']), esc_html(tli_reason_label($m['reason'])), $m['qty_change'] >= 0 ? 'up' : 'down', $m['qty_change'] >= 0 ? '+' : '', (int) $m['qty_change'],
            esc_html((string) ($m['qty_before'] ?? '—')), esc_html((string) ($m['qty_after'] ?? '—')),
            $m['order_id'] ? '<a class="tli-link" href="' . esc_url(($o = wc_get_order((int) $m['order_id'])) ? $o->get_edit_order_url() : '#') . '">#' . (int) $m['order_id'] . '</a>' : '—',
            esc_html($u ? $u->display_name : 'System'), esc_html($m['note']));
    }
    echo '</tbody></table></div>';
    tli_foot();
}

/* ── Settings ─────────────────────────────────────────────────────────── */
/** Time of the newest daily backup in the backups volume, or null. */
function tli_last_backup(): ?int
{
    $files = glob('/backups/db-*.sql.gz') ?: [];
    return $files ? max(array_map('filemtime', $files)) : null;
}

function tli_page_settings(): void
{
    if (isset($_POST['tli_low_stock']) && check_admin_referer('tli_settings') && current_user_can('tl_manage')) {
        update_option('tli_low_stock', max(0, (int) $_POST['tli_low_stock']));
        update_option('tli_alert_email', sanitize_email(wp_unslash($_POST['tli_alert_email'] ?? '')));
        echo '<div class="notice notice-success"><p>Saved.</p></div>';
    }
    tli_head('Settings', 'How TL Commerce Hub works for this store.');
    tli_launch_card();
    echo '<div class="tli-card"><form method="post">';
    wp_nonce_field('tli_settings');
    printf('<label class="tli-field"><span>Low stock level (pieces per size)</span><input class="tli-input" type="number" min="0" name="tli_low_stock" value="%d" style="max-width:120px"></label>', (int) get_option('tli_low_stock', 5));
    printf('<label class="tli-field"><span>Send daily alerts to</span><input class="tli-input" type="email" name="tli_alert_email" value="%s" placeholder="%s" style="max-width:320px"></label>', esc_attr((string) get_option('tli_alert_email', '')), esc_attr(get_option('admin_email')));
    echo '<p class="muted small">A size at or below the low stock level shows as Low stock. Every morning an email lists low and out-of-stock sizes and orders waiting more than 24 hours to be packed (only when there is something to report).</p><p><button class="tli-btn primary">Save</button></p></form></div>';
    tli_settings_invoice_card();
    $bk = tli_last_backup();
    $mail = function_exists('schermo_mail_ready') && schermo_mail_ready();
    $me = get_option('schermo_mail_error');
    printf('<div class="tli-card"><h2>System</h2><ul class="tli-help"><li><b>Backups:</b> %s. Database and uploaded files are saved every day and kept for 14 days on the server. Restore: <code>docker exec -it schermo-wordpress-1 /opt/schermo/restore.sh YYYY-MM-DD</code></li><li><b>Email:</b> %s</li></ul></div>',
        $bk ? 'last one ' . esc_html(human_time_diff($bk)) . ' ago' : (is_dir('/backups') ? 'none yet (the first runs about 10 minutes after a deploy)' : 'not available on this server'),
        $mail ? ($me ? 'set up, but the last send failed: ' . esc_html((string) $me['error']) : 'set up and working.') : 'NOT set up — no email leaves the site. Add SMTP_HOST, SMTP_USER, SMTP_PASS and SMTP_FROM (from a mail service such as Zoho Mail, Brevo or Google Workspace) to <code>/opt/schermo/schermo.env</code> on the server and redeploy.');
    echo '<div class="tli-card"><h2>Team</h2><p class="muted">Add people in <a href="' . esc_url(admin_url('user-new.php')) . '">Users → Add New</a> and choose a role:</p><ul class="tli-help"><li><b>Warehouse staff</b> — sees the hub, adjusts stock, packs and dispatches orders, records returns.</li><li><b>Customer support</b> — sees the hub and orders, records returns. Cannot change stock.</li><li><b>Shop manager / Administrator</b> — everything, including Stores, Connection and Settings.</li></ul></div>';
    echo '<div class="tli-card"><h2>How it works</h2><ul class="tli-help"><li>The hub site holds the central stock (WooCommerce stock on the hub). Storefronts connected on the Stores page send their paid orders to the hub, which takes the stock once, and read stock back every 5 minutes.</li><li>Every change is written to Stock movements with who, when and why: adjustments here, orders (sold / cancelled) and edits in the WooCommerce product screen.</li><li>Order stages: Paid → Packed → Dispatched → Delivered. Use the Next step button on Orders.</li><li>Sizes marked "Not counted yet" are sold without a limit until you record a count with Adjust → Stocktake.</li></ul></div>';
    tli_foot();
}
