<?php
/**
 * Purchasing: supplier purchase orders and incoming stock (hub only — storefronts sell from the hub's stock).
 * A PO is drafted, sent to the supplier ("ordered"), then received at the warehouse in one or more deliveries.
 * Good units go into stock through the normal ledger (reason "Stock received", note "PO #n · supplier");
 * damaged units are recorded on the PO and never enter stock. What is still to come shows as "incoming".
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

const TLI_PO_STATES = [
    'draft' => ['Draft', 'neutral'], 'ordered' => ['Ordered — awaiting delivery', 'info'], 'partial' => ['Partly received', 'warn'],
    'received' => ['Received', 'ok'], 'cancelled' => ['Cancelled', 'neutral'],
];

function tli_po_tables(): array
{
    global $wpdb;
    return [$wpdb->prefix . 'tl_purchase_orders', $wpdb->prefix . 'tl_po_lines'];
}

function tli_install_purchasing(): void
{
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    [$po, $ln] = tli_po_tables();
    $cs = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE {$po} (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        supplier varchar(150) NOT NULL,
        reference varchar(100) NOT NULL DEFAULT '',
        status varchar(16) NOT NULL DEFAULT 'draft',
        expected_date date NULL,
        warehouse_id bigint unsigned NOT NULL DEFAULT 0,
        note text NULL,
        created_by bigint unsigned NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY status (status)
    ) {$cs};");
    dbDelta("CREATE TABLE {$ln} (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        po_id bigint unsigned NOT NULL,
        sku varchar(100) NOT NULL,
        product_id bigint unsigned NOT NULL DEFAULT 0,
        qty_ordered int unsigned NOT NULL DEFAULT 0,
        qty_received int unsigned NOT NULL DEFAULT 0,
        qty_damaged int unsigned NOT NULL DEFAULT 0,
        unit_cost decimal(12,2) NULL,
        PRIMARY KEY  (id),
        KEY po_id (po_id),
        KEY sku (sku)
    ) {$cs};");
}

function tli_po(int $id): ?array
{
    global $wpdb;
    [$po, $ln] = tli_po_tables();
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$po} WHERE id = %d", $id), ARRAY_A);
    if (!$row) {
        return null;
    }
    $row['lines'] = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$ln} WHERE po_id = %d ORDER BY id", $id), ARRAY_A) ?: [];
    return $row;
}

/** Units still to arrive, by SKU, from POs sent to suppliers. */
function tli_incoming_map(): array
{
    global $wpdb;
    if (tli_mode() !== 'hub') {
        return [];
    }
    [$po, $ln] = tli_po_tables();
    $rows = $wpdb->get_results("SELECT l.sku, SUM(GREATEST(CAST(l.qty_ordered AS SIGNED) - l.qty_received - l.qty_damaged, 0)) AS q FROM {$ln} l JOIN {$po} p ON p.id = l.po_id WHERE p.status IN ('ordered','partial') GROUP BY l.sku", ARRAY_A) ?: [];
    $out = [];
    foreach ($rows as $r) {
        if ((int) $r['q'] > 0) {
            $out[$r['sku']] = (int) $r['q'];
        }
    }
    return $out;
}

/** Label for a SKU: "Product · Size". */
function tli_sku_label(string $sku, int &$pid = 0): string
{
    $pid = $sku !== '' ? (int) wc_get_product_id_by_sku($sku) : 0;
    $p = $pid ? wc_get_product($pid) : null;
    if (!$p) {
        return '';
    }
    if ($p->is_type('variation')) {
        $parent = wc_get_product($p->get_parent_id());
        $size = strtoupper((string) $p->get_attribute('pa_size'));
        return ($parent ? $parent->get_name() : $p->get_name()) . ($size !== '' ? ' · ' . $size : '');
    }
    return $p->get_name();
}

add_action('admin_menu', static function (): void {
    if (tli_mode() === 'hub') {
        add_submenu_page('tl-inventory', 'Purchasing', 'Purchasing', 'tl_adjust', 'tl-purchasing', 'tli_page_purchasing', 5);
    }
}, 11);

function tli_po_back(string $msg, bool $ok = true, int $po = 0): void
{
    $url = admin_url('admin.php?page=tl-purchasing' . ($po ? '&po=' . $po : ''));
    wp_safe_redirect(add_query_arg(['tli_msg' => rawurlencode($msg), 'tli_ok' => $ok ? '1' : '0'], $url));
    exit;
}

function tli_po_guard(string $nonce): void
{
    if (!current_user_can('tl_adjust') || tli_mode() !== 'hub') {
        wp_die('Not allowed.', 403);
    }
    check_admin_referer($nonce);
}

/* Create / edit a PO (only drafts can be edited). */
add_action('admin_post_tli_po_save', static function (): void {
    global $wpdb;
    tli_po_guard('tli_po_save');
    [$po, $ln] = tli_po_tables();
    $id = absint($_POST['po'] ?? 0);
    $supplier = sanitize_text_field(wp_unslash($_POST['supplier'] ?? ''));
    $lines = [];
    $bad = [];
    foreach ((array) ($_POST['sku'] ?? []) as $i => $sku) {
        $sku = sanitize_text_field(wp_unslash($sku));
        $qty = absint($_POST['qty'][$i] ?? 0);
        if ($sku === '' || $qty === 0) {
            continue;
        }
        $pid = 0;
        if (tli_sku_label($sku, $pid) === '') {
            $bad[] = $sku;
            continue;
        }
        $cost = trim((string) ($_POST['cost'][$i] ?? ''));
        $lines[$sku] = ['sku' => $sku, 'product_id' => $pid, 'qty_ordered' => ($lines[$sku]['qty_ordered'] ?? 0) + $qty, 'unit_cost' => $cost === '' ? null : round((float) $cost, 2)];
    }
    if ($supplier === '' || !$lines || $bad) {
        tli_po_back($bad ? 'Unknown SKU: ' . implode(', ', $bad) . '. Nothing saved.' : 'Add a supplier and at least one item with a quantity.', false, $id);
    }
    if ($id && (!($cur = tli_po($id)) || $cur['status'] !== 'draft')) {
        tli_po_back('Only draft purchase orders can be edited.', false, $id);
    }
    $now = current_time('mysql', true);
    $expected = sanitize_text_field(wp_unslash($_POST['expected'] ?? ''));
    $data = ['supplier' => $supplier, 'reference' => sanitize_text_field(wp_unslash($_POST['reference'] ?? '')), 'expected_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $expected) ? $expected : null,
        'note' => sanitize_textarea_field(wp_unslash($_POST['note'] ?? '')), 'status' => isset($_POST['send']) ? 'ordered' : 'draft', 'updated_at' => $now,
        'warehouse_id' => function_exists('tli_wh') && tli_wh(absint($_POST['warehouse'] ?? 0)) ? absint($_POST['warehouse']) : (function_exists('tli_wh_default') ? tli_wh_default() : 0)];
    if ($id) {
        $wpdb->update($po, $data, ['id' => $id]);
        $wpdb->delete($ln, ['po_id' => $id]);
    } else {
        $wpdb->insert($po, $data + ['created_by' => get_current_user_id(), 'created_at' => $now]);
        $id = (int) $wpdb->insert_id;
    }
    foreach ($lines as $l) {
        $wpdb->insert($ln, $l + ['po_id' => $id]);
    }
    tli_po_back($data['status'] === 'ordered' ? 'Purchase order saved and marked as ordered. Its items now show as incoming.' : 'Draft saved.', true, $id);
});

add_action('admin_post_tli_po_status', static function (): void {
    global $wpdb;
    $id = absint($_POST['po'] ?? 0);
    tli_po_guard('tli_po_status_' . $id);
    $to = sanitize_key($_POST['to'] ?? '');
    $cur = tli_po($id);
    $allowed = ['draft' => ['ordered', 'cancelled'], 'ordered' => ['cancelled', 'draft'], 'partial' => ['received'], 'received' => [], 'cancelled' => ['draft']];
    if (!$cur || !in_array($to, $allowed[$cur['status']] ?? [], true)) {
        tli_po_back('That change is not possible for this purchase order.', false, $id);
    }
    if ($cur['status'] === 'ordered' && $to === 'draft' && array_sum(array_column($cur['lines'], 'qty_received')) > 0) {
        tli_po_back('Items were already received on this order.', false, $id);
    }
    $wpdb->update(tli_po_tables()[0], ['status' => $to, 'updated_at' => current_time('mysql', true)], ['id' => $id]);
    $msg = ['ordered' => 'Marked as ordered. Its items now show as incoming.', 'cancelled' => 'Purchase order cancelled.', 'draft' => 'Back to draft — you can edit it now.', 'received' => 'Closed as received. Anything not delivered is no longer counted as incoming.'][$to];
    tli_po_back($msg, true, $id);
});

/* Receive a delivery against a PO. */
add_action('admin_post_tli_po_receive', static function (): void {
    global $wpdb;
    $id = absint($_POST['po'] ?? 0);
    tli_po_guard('tli_po_receive_' . $id);
    $cur = tli_po($id);
    if (!$cur || !in_array($cur['status'], ['ordered', 'partial'], true)) {
        tli_po_back('Only ordered purchase orders can be received.', false, $id);
    }
    $ln = tli_po_tables()[1];
    $note_extra = sanitize_text_field(wp_unslash($_POST['note'] ?? ''));
    $good_total = $dmg_total = 0;
    $errors = [];
    foreach ($cur['lines'] as $l) {
        $good = absint($_POST['good'][$l['id']] ?? 0);
        $dmg = absint($_POST['damaged'][$l['id']] ?? 0);
        if (!$good && !$dmg) {
            continue;
        }
        $pid = (int) $l['product_id'] ?: (int) wc_get_product_id_by_sku($l['sku']);
        if ($good) {
            try {
                tli_adjust($pid, 'received', $good, mb_substr(sprintf('PO #%d · %s%s', $id, $cur['supplier'], $note_extra !== '' ? ' · ' . $note_extra : ''), 0, 200), null, (int) ($cur['warehouse_id'] ?? 0) ?: null);
            } catch (Throwable $e) {
                $errors[] = $l['sku'] . ': ' . $e->getMessage();
                continue;
            }
        }
        $wpdb->query($wpdb->prepare("UPDATE {$ln} SET qty_received = qty_received + %d, qty_damaged = qty_damaged + %d WHERE id = %d", $good, $dmg, (int) $l['id']));
        $good_total += $good;
        $dmg_total += $dmg;
    }
    $after = tli_po($id);
    $left = 0;
    $any = 0;
    foreach ($after['lines'] as $l) {
        $left += max(0, (int) $l['qty_ordered'] - (int) $l['qty_received'] - (int) $l['qty_damaged']);
        $any += (int) $l['qty_received'] + (int) $l['qty_damaged'];
    }
    $status = $left === 0 ? 'received' : ($any > 0 ? 'partial' : $after['status']);
    $wpdb->update(tli_po_tables()[0], ['status' => $status, 'updated_at' => current_time('mysql', true)], ['id' => $id]);
    if (!$good_total && !$dmg_total && !$errors) {
        tli_po_back('Enter the quantities that arrived.', false, $id);
    }
    $msg = sprintf('Received %d into stock%s.%s', $good_total, $dmg_total ? sprintf(', %d damaged (not added to stock)', $dmg_total) : '', $left ? sprintf(' %d still to come.', $left) : ' Order complete.');
    tli_po_back($msg . ($errors ? ' Problems: ' . implode('; ', $errors) : ''), !$errors, $id);
});

/* Printable PO for the supplier (quantities and costs, no stock levels). */
add_action('admin_post_tli_po_print', static function (): void {
    if (!current_user_can('tl_adjust')) {
        wp_die('Not allowed.', 403);
    }
    check_admin_referer('tli_po_print');
    $po = tli_po(absint($_GET['po'] ?? 0));
    if (!$po) {
        wp_die('Purchase order not found.');
    }
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>PO #' . (int) $po['id'] . ' · ' . esc_html(get_bloginfo('name')) . '</title><style>' . tli_print_css() . '</style></head><body>';
    echo '<div class="bar no-print"><b>Purchase order #' . (int) $po['id'] . '</b><button onclick="window.print()">Print</button></div>';
    printf('<header class="doc-head"><div><img class="logo" src="%s" alt=""><p class="muted">%s</p></div><div class="right"><h1>Purchase order</h1><p><b>#%d</b>%s<br>%s</p></div></header>',
        esc_url(get_stylesheet_directory_uri() . '/assets/img/email-logo.png'), esc_html(get_bloginfo('name')), (int) $po['id'], $po['reference'] !== '' ? ' · Ref ' . esc_html($po['reference']) : '', esc_html(wp_date('d M Y', strtotime($po['created_at'] . ' UTC'))));
    printf('<div class="cols"><div><h2>Supplier</h2><p class="addr">%s</p></div><div><h2>Deliver to</h2><p class="addr">%s<br>%s</p>%s</div></div>',
        esc_html($po['supplier']), esc_html(get_bloginfo('name')), nl2br(esc_html(($pw = function_exists('tli_wh') ? tli_wh((int) ($po['warehouse_id'] ?? 0)) : null) ? trim($pw['name'] . "\n" . ($pw['address'] ?: trim($pw['city'] . ', ' . $pw['country'], ', '))) : '')), $po['expected_date'] ? '<p>Expected by: <b>' . esc_html(wp_date('d M Y', strtotime($po['expected_date']))) . '</b></p>' : '');
    echo '<table><thead><tr><th>SKU</th><th>Item</th><th class="n">Qty</th><th class="n">Unit cost</th><th class="n">Line total</th></tr></thead><tbody>';
    $total = 0.0;
    $costed = true;
    foreach ($po['lines'] as $l) {
        $line = $l['unit_cost'] === null ? null : (float) $l['unit_cost'] * (int) $l['qty_ordered'];
        $costed = $costed && $line !== null;
        $total += (float) $line;
        printf('<tr><td class="mono">%s</td><td>%s</td><td class="n big">%d</td><td class="n">%s</td><td class="n">%s</td></tr>', esc_html($l['sku']), esc_html(tli_sku_label($l['sku'])), (int) $l['qty_ordered'],
            $l['unit_cost'] === null ? '—' : esc_html(number_format_i18n((float) $l['unit_cost'], 2)), $line === null ? '—' : esc_html(number_format_i18n($line, 2)));
    }
    printf('</tbody></table><p class="sum"><b>%d</b> pieces%s</p>', array_sum(array_column($po['lines'], 'qty_ordered')), $costed && $po['lines'] ? ' · Total <b>₹' . esc_html(number_format_i18n($total, 2)) . '</b> (before tax)' : '');
    if (!empty($po['note'])) {
        echo '<p class="note"><b>Note:</b> ' . nl2br(esc_html($po['note'])) . '</p>';
    }
    echo '<p class="foot">Please quote PO #' . (int) $po['id'] . ' on your invoice and delivery challan.<br><br>Authorised by: ____________________</p>';
    echo '<script>if (location.hash !== "#noprint") setTimeout(() => window.print(), 300);</script></body></html>';
    exit;
});

/* ── Screens ──────────────────────────────────────────────────────────── */
function tli_page_purchasing(): void
{
    $po = sanitize_text_field(wp_unslash($_GET['po'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification
    if ($po === 'new' || isset($_GET['edit'])) { // phpcs:ignore WordPress.Security.NonceVerification
        tli_po_form($po === 'new' ? null : tli_po(absint($po)));
    } elseif ($po !== '' && ($row = tli_po(absint($po)))) {
        tli_po_detail($row);
    } else {
        tli_po_list();
    }
}

function tli_po_list(): void
{
    global $wpdb;
    [$po, $ln] = tli_po_tables();
    $status = sanitize_key($_GET['status'] ?? 'open'); // phpcs:ignore WordPress.Security.NonceVerification
    $where = ['open' => "p.status IN ('draft','ordered','partial')", 'received' => "p.status = 'received'", 'cancelled' => "p.status = 'cancelled'", 'all' => '1=1'][$status] ?? '1=1';
    $rows = $wpdb->get_results("SELECT p.*, COALESCE(SUM(l.qty_ordered),0) AS ordered, COALESCE(SUM(l.qty_received),0) AS received, COALESCE(SUM(l.qty_damaged),0) AS damaged, COUNT(l.id) AS items
        FROM {$po} p LEFT JOIN {$ln} l ON l.po_id = p.id WHERE {$where} GROUP BY p.id ORDER BY p.id DESC LIMIT 200", ARRAY_A) ?: [];
    $base = admin_url('admin.php?page=tl-purchasing');
    tli_head('Purchasing', 'Purchase orders to suppliers and the stock on its way to your warehouses.',
        '<a class="tli-btn" href="' . esc_url($base . '&po=new&prefill=low') . '">Reorder low stock</a><a class="tli-btn primary" href="' . esc_url($base . '&po=new') . '">New purchase order</a>');

    $incoming = tli_incoming_map();
    $late = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$po} WHERE status IN ('ordered','partial') AND expected_date IS NOT NULL AND expected_date < '" . esc_sql(wp_date('Y-m-d')) . "'");
    $open = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$po} WHERE status IN ('ordered','partial')");
    echo '<div class="tli-kpis">';
    printf('<div class="tli-kpi"><span class="k-label">Open orders</span><span class="k-value">%d</span><span class="k-hint">Sent to suppliers, not fully received</span></div>', $open);
    printf('<div class="tli-kpi"><span class="k-label">Units incoming</span><span class="k-value">%s</span><span class="k-hint">Across %d sizes / SKUs</span></div>', esc_html(number_format_i18n(array_sum($incoming))), count($incoming));
    printf('<div class="tli-kpi %s"><span class="k-label">Overdue</span><span class="k-value">%d</span><span class="k-hint">Past the expected date</span></div>', $late ? 'is-bad' : '', $late);
    echo '</div><nav class="tli-seg mb">';
    foreach (['open' => 'Open', 'received' => 'Received', 'cancelled' => 'Cancelled', 'all' => 'All'] as $k => $l) {
        printf('<a class="%s" href="%s">%s</a>', $status === $k ? 'on' : '', esc_url($base . '&status=' . $k), esc_html($l));
    }
    echo '</nav><div class="tli-card flush"><table class="tli-table"><thead><tr><th>PO</th><th>Supplier</th><th>Status</th><th>Expected</th><th class="num">Items</th><th class="num">Ordered</th><th class="num">Received</th><th class="num">Still to come</th><th></th></tr></thead><tbody>';
    if (!$rows) {
        echo '<tr><td colspan="9"><div class="tli-empty"><h3>No purchase orders here</h3><p>Create one when you order stock from a supplier — its items show as incoming until they arrive, and receiving them adds them to stock.</p></div></td></tr>';
    }
    foreach ($rows as $r) {
        [$label, $tone] = TLI_PO_STATES[$r['status']] ?? [$r['status'], 'neutral'];
        $left = in_array($r['status'], ['ordered', 'partial'], true) ? max(0, (int) $r['ordered'] - (int) $r['received'] - (int) $r['damaged']) : 0;
        $overdue = $left && $r['expected_date'] && $r['expected_date'] < wp_date('Y-m-d');
        printf('<tr><td class="nowrap"><a class="tli-link" href="%s"><b>#%d</b></a><span class="muted small">%s</span></td><td>%s%s</td><td>%s</td><td>%s</td><td class="num">%d</td><td class="num">%d</td><td class="num">%d%s</td><td class="num"><b>%s</b></td><td class="num"><a class="tli-btn sm ghost" href="%1$s">Open</a></td></tr>',
            esc_url($base . '&po=' . $r['id']), (int) $r['id'], esc_html(wp_date('d M Y', strtotime($r['created_at'] . ' UTC'))), esc_html($r['supplier']), $r['reference'] !== '' ? '<span class="muted small">Ref ' . esc_html($r['reference']) . '</span>' : '',
            tli_badge($label, $tone), $r['expected_date'] ? ($overdue ? tli_badge('Overdue · ' . wp_date('d M', strtotime($r['expected_date'])), 'bad') : esc_html(wp_date('d M Y', strtotime($r['expected_date'])))) : '<span class="muted">—</span>',
            (int) $r['items'], (int) $r['ordered'], (int) $r['received'], (int) $r['damaged'] ? ' <span class="muted small">+' . (int) $r['damaged'] . ' damaged</span>' : '', $left ? (int) $left : '—');
    }
    echo '</tbody></table></div>';
    tli_foot();
}

/** Every SKU that can be ordered, for the item picker. */
function tli_sku_options(): array
{
    $opts = [];
    foreach (tli_inventory_rows() as $g) {
        foreach ($g['rows'] as $r) {
            if ($r['sku'] !== '') {
                $opts[$r['sku']] = $g['product']->get_name() . ($r['size'] !== '—' ? ' · ' . $r['size'] : '') . ' — ' . ($r['qty'] === null ? 'not counted' : $r['qty'] . ' in stock');
            }
        }
    }
    return $opts;
}

function tli_po_form(?array $po): void
{
    $lines = $po['lines'] ?? [];
    $prefilled = 0;
    if (!$po && ($_GET['prefill'] ?? '') === 'low') { // phpcs:ignore WordPress.Security.NonceVerification
        $level = (int) get_option('tli_low_stock', 5);
        $incoming = tli_incoming_map();
        foreach (tli_inventory_rows() as $g) {
            foreach ($g['rows'] as $r) {
                if (in_array($r['state'], ['low', 'out'], true) && $r['sku'] !== '' && empty($incoming[$r['sku']])) {
                    // Suggestion only: enough to reach twice the reorder level. Change it to what you actually order.
                    $lines[] = ['sku' => $r['sku'], 'qty_ordered' => max(1, 2 * $level - (int) $r['qty']), 'unit_cost' => null];
                    $prefilled++;
                }
            }
        }
    }
    $lines[] = ['sku' => '', 'qty_ordered' => '', 'unit_cost' => null];
    $lines[] = ['sku' => '', 'qty_ordered' => '', 'unit_cost' => null];
    tli_head($po ? 'Edit purchase order #' . (int) $po['id'] : 'New purchase order', 'List what you are ordering from one supplier. Nothing changes in stock until the delivery is received.',
        '<a class="tli-btn" href="' . esc_url(admin_url('admin.php?page=tl-purchasing')) . '">Back to purchasing</a>');
    if ($prefilled) {
        printf('<div class="tli-flash is-ok">Added %d low or out-of-stock sizes that have nothing on order. Quantities are only a suggestion (enough to reach twice the reorder level) — change them to what you actually order, and remove rows you don\'t need by clearing the quantity.</div>', $prefilled);
    } elseif (!$po && ($_GET['prefill'] ?? '') === 'low') { // phpcs:ignore WordPress.Security.NonceVerification
        echo '<div class="tli-flash is-ok">No low or out-of-stock sizes without an open order — nothing to add automatically.</div>';
    }
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="tli-po-form">';
    wp_nonce_field('tli_po_save');
    printf('<input type="hidden" name="action" value="tli_po_save"><input type="hidden" name="po" value="%d">', (int) ($po['id'] ?? 0));
    printf('<div class="tli-card"><div class="tli-po-head"><label class="tli-field"><span>Supplier *</span><input class="tli-input" name="supplier" required maxlength="150" value="%s" placeholder="Supplier or factory name"></label><label class="tli-field"><span>Supplier reference</span><input class="tli-input" name="reference" maxlength="100" value="%s" placeholder="Quotation / proforma no. (optional)"></label><label class="tli-field"><span>Expected delivery</span><input class="tli-input" type="date" name="expected" value="%s"></label></div><label class="tli-field"><span>Note for the supplier (optional)</span><textarea class="tli-input tli-textarea" name="note" rows="2" maxlength="1000">%s</textarea></label></div>',
        esc_attr($po['supplier'] ?? ''), esc_attr($po['reference'] ?? ''), esc_attr($po['expected_date'] ?? ''), esc_textarea($po['note'] ?? ''));
    if (function_exists('tli_wh_multi') && tli_wh_multi()) {
        $opts = '';
        foreach (tli_warehouses() as $w) {
            $opts .= sprintf('<option value="%d"%s>%s</option>', (int) $w['id'], selected((int) ($po['warehouse_id'] ?? tli_wh_default()), (int) $w['id'], false), esc_html($w['name']));
        }
        echo '<div class="tli-card"><label class="tli-field"><span>Deliver to warehouse</span><select class="tli-input" name="warehouse">' . $opts . '</select></label></div>';
    }
    echo '<datalist id="tli-skus">';
    foreach (tli_sku_options() as $sku => $label) {
        printf('<option value="%s">%s</option>', esc_attr($sku), esc_html($label));
    }
    echo '</datalist><div class="tli-card flush"><table class="tli-table tli-po-lines"><thead><tr><th>SKU (type to search)</th><th>Item</th><th class="num">Quantity</th><th class="num">Unit cost ₹ (optional)</th></tr></thead><tbody data-po-lines>';
    foreach ($lines as $l) {
        printf('<tr><td><input class="tli-input" name="sku[]" list="tli-skus" value="%s" placeholder="e.g. MI-001-M" autocomplete="off"></td><td class="small" data-label>%s</td><td class="num"><input class="tli-input sm" type="number" min="0" step="1" name="qty[]" value="%s"></td><td class="num"><input class="tli-input sm" type="number" min="0" step="0.01" name="cost[]" value="%s"></td></tr>',
            esc_attr($l['sku']), esc_html($l['sku'] !== '' ? tli_sku_label($l['sku']) : ''), esc_attr((string) $l['qty_ordered']), esc_attr($l['unit_cost'] === null ? '' : (string) $l['unit_cost']));
    }
    echo '</tbody></table><div class="tli-po-foot"><button type="button" class="tli-btn sm" data-po-add>+ Add row</button></div></div>';
    echo '<p class="tli-po-actions"><button class="tli-btn" name="draft" value="1">Save as draft</button><button class="tli-btn primary" name="send" value="1">Save and mark as ordered</button></p></form>';
    tli_foot();
}

function tli_po_detail(array $po): void
{
    $id = (int) $po['id'];
    [$label, $tone] = TLI_PO_STATES[$po['status']] ?? [$po['status'], 'neutral'];
    $post = esc_url(admin_url('admin-post.php'));
    $btn = static function (string $to, string $text, string $cls = '') use ($id, $post): string {
        return sprintf('<form method="post" action="%s" class="tli-inline-form"><input type="hidden" name="action" value="tli_po_status"><input type="hidden" name="po" value="%d"><input type="hidden" name="to" value="%s">%s<button class="tli-btn %s">%s</button></form>',
            $post, $id, esc_attr($to), wp_nonce_field('tli_po_status_' . $id, '_wpnonce', true, false), esc_attr($cls), esc_html($text));
    };
    $actions = '<a class="tli-btn" href="' . esc_url(admin_url('admin.php?page=tl-purchasing')) . '">All purchase orders</a>';
    $actions .= '<a class="tli-btn" target="_blank" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=tli_po_print&po=' . $id), 'tli_po_print')) . '">Print PO</a>';
    if ($po['status'] === 'draft') {
        $actions .= '<a class="tli-btn" href="' . esc_url(admin_url('admin.php?page=tl-purchasing&po=' . $id . '&edit=1')) . '">Edit</a>' . $btn('cancelled', 'Cancel order') . $btn('ordered', 'Mark as ordered', 'primary');
    } elseif ($po['status'] === 'ordered') {
        $actions .= $btn('draft', 'Back to draft') . $btn('cancelled', 'Cancel order');
    } elseif ($po['status'] === 'partial') {
        $actions .= $btn('received', 'Close — nothing more coming');
    } elseif ($po['status'] === 'cancelled') {
        $actions .= $btn('draft', 'Reopen as draft');
    }
    tli_head('Purchase order #' . $id, $po['supplier'] . ($po['reference'] !== '' ? ' · Ref ' . $po['reference'] : ''), $actions);
    $receiving = in_array($po['status'], ['ordered', 'partial'], true);
    $overdue = $receiving && $po['expected_date'] && $po['expected_date'] < wp_date('Y-m-d');
    printf('<div class="tli-card tli-po-meta"><div><span class="muted small">Status</span>%s</div><div><span class="muted small">Created</span><b>%s</b></div><div><span class="muted small">Expected delivery</span>%s</div><div><span class="muted small">Created by</span><b>%s</b></div></div>',
        tli_badge($label, $tone), esc_html(wp_date('d M Y', strtotime($po['created_at'] . ' UTC'))), $po['expected_date'] ? ($overdue ? tli_badge('Overdue · ' . wp_date('d M Y', strtotime($po['expected_date'])), 'bad') : '<b>' . esc_html(wp_date('d M Y', strtotime($po['expected_date']))) . '</b>') : '<b>—</b>',
        esc_html(($u = get_userdata((int) $po['created_by'])) ? $u->display_name : '—'));
    if (!empty($po['note'])) {
        echo '<div class="tli-flash is-ok">' . nl2br(esc_html($po['note'])) . '</div>';
    }
    if ($receiving) {
        echo '<form method="post" action="' . $post . '">';
        wp_nonce_field('tli_po_receive_' . $id);
        printf('<input type="hidden" name="action" value="tli_po_receive"><input type="hidden" name="po" value="%d">', $id);
    }
    echo '<div class="tli-card flush"><table class="tli-table"><thead><tr><th>SKU</th><th>Item</th><th class="num">Ordered</th><th class="num">Received</th><th class="num">Damaged</th><th class="num">Still to come</th><th class="num">Unit cost</th>' . ($receiving ? '<th class="num">Arrived now · good</th><th class="num">Arrived now · damaged</th>' : '') . '</tr></thead><tbody>';
    $total = 0.0;
    foreach ($po['lines'] as $l) {
        $left = max(0, (int) $l['qty_ordered'] - (int) $l['qty_received'] - (int) $l['qty_damaged']);
        $total += $l['unit_cost'] === null ? 0 : (float) $l['unit_cost'] * (int) $l['qty_ordered'];
        printf('<tr><td class="mono">%s</td><td>%s</td><td class="num">%d</td><td class="num">%d</td><td class="num">%s</td><td class="num"><b>%s</b></td><td class="num">%s</td>', esc_html($l['sku']), esc_html(tli_sku_label($l['sku']) ?: 'Product no longer exists'),
            (int) $l['qty_ordered'], (int) $l['qty_received'], (int) $l['qty_damaged'] ?: '—', $left ?: '—', $l['unit_cost'] === null ? '—' : esc_html(number_format_i18n((float) $l['unit_cost'], 2)));
        if ($receiving) {
            printf('<td class="num"><input class="tli-input sm" type="number" min="0" step="1" name="good[%d]" placeholder="%d"></td><td class="num"><input class="tli-input sm" type="number" min="0" step="1" name="damaged[%1$d]" placeholder="0"></td>', (int) $l['id'], $left);
        }
        echo '</tr>';
    }
    echo '</tbody></table>';
    if ($total > 0) {
        echo '<div class="tli-po-foot"><span class="muted">Order value (before tax): <b>₹' . esc_html(number_format_i18n($total, 2)) . '</b></span></div>';
    }
    echo '</div>';
    if ($receiving) {
        echo '<div class="tli-card"><h2>Receive a delivery</h2><p class="muted">Count what arrived and enter it above. Good pieces go straight into stock (shown in Stock movements as "Stock received · PO #' . $id . '"). Damaged pieces are only recorded here and are not added to stock. Short deliveries stay open for the rest.</p>';
        echo '<label class="tli-field"><span>Delivery note (optional)</span><input class="tli-input" name="note" maxlength="120" placeholder="e.g. Challan 5521, 2 cartons"></label><p><button class="tli-btn primary">Add to stock</button></p></div></form>';
    } elseif ($po['status'] === 'draft') {
        echo '<div class="tli-card"><p class="muted">This is a draft — its items are not counted as incoming yet. Print it or send it to the supplier, then press <b>Mark as ordered</b>.</p></div>';
    }
    tli_foot();
}
