<?php
/**
 * Printable warehouse documents: a pick list (every item to collect for the chosen orders, grouped by SKU) and
 * packing slips (one page per order, no prices). Opened in a new tab and sent to the printer.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/** Normalises chosen orders ("L:12" = this store's order, "R:5" = another store's) into one shape. */
function tli_print_orders(array $keys): array
{
    global $wpdb;
    $out = [];
    $names = array_column(tli_mode() === 'hub' ? tli_stores() : [], 'name', 'id');
    foreach ($keys as $k) {
        [$type, $id] = array_pad(explode(':', (string) $k, 2), 2, '0');
        $id = absint($id);
        if ($type === 'L' && ($o = wc_get_order($id))) {
            $lines = [];
            foreach ($o->get_items() as $item) {
                $p = $item->get_product();
                $lines[] = ['sku' => $p ? (string) $p->get_sku() : '', 'qty' => (int) $item->get_quantity(), 'name' => $item->get_name()];
            }
            $out[] = ['store' => get_bloginfo('name'), 'number' => $o->get_order_number(), 'date' => $o->get_date_created() ? $o->get_date_created()->getTimestamp() : time(),
                'customer' => trim($o->get_formatted_billing_full_name()), 'ship_to' => wp_strip_all_tags(str_replace(['<br/>', '<br>'], "\n", (string) ($o->get_formatted_shipping_address() ?: $o->get_formatted_billing_address()))),
                'phone' => (string) ($o->get_shipping_phone() ?: $o->get_billing_phone()), 'note' => (string) $o->get_customer_note(), 'lines' => $lines, 'shipping' => $o->get_shipping_method()];
        } elseif ($type === 'R' && tli_mode() === 'hub') {
            $r = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tl_remote_orders WHERE id = %d", $id), ARRAY_A);
            if ($r) {
                $out[] = ['store' => $names[$r['store_id']] ?? $r['store_id'], 'number' => $r['number'], 'date' => strtotime($r['placed_at'] . ' UTC'), 'customer' => $r['customer'],
                    'ship_to' => (string) ($r['ship_to'] ?? ''), 'phone' => (string) ($r['phone'] ?? ''), 'note' => (string) ($r['note'] ?? ''), 'lines' => json_decode($r['lines_json'], true) ?: [], 'shipping' => ''];
            }
        }
    }
    return $out;
}

/** Keys of every order waiting to be packed (this store + other stores). */
function tli_to_pack_keys(): array
{
    $keys = array_map(static fn ($id) => 'L:' . $id, wc_get_orders(['status' => ['processing'], 'limit' => -1, 'return' => 'ids', 'orderby' => 'date', 'order' => 'ASC']));
    if (tli_mode() === 'hub') {
        foreach (tli_remote_orders('processing', 500) as $r) {
            $keys[] = 'R:' . $r['id'];
        }
    }
    return $keys;
}

add_action('admin_post_tli_print', static function (): void {
    if (!current_user_can('tl_view')) {
        wp_die('Not allowed.', 403);
    }
    check_admin_referer('tli_print');
    $what = sanitize_key($_REQUEST['what'] ?? 'pick');
    $keys = array_map('sanitize_text_field', (array) wp_unslash($_REQUEST['o'] ?? []));
    if (!empty($_REQUEST['all_to_pack'])) {
        $keys = tli_to_pack_keys();
    }
    if ($what === 'invoice' || $what === 'creditnote') {
        $list = [];
        $skipped = [];
        $extra = '';
        foreach ($keys as $k) {
            if (str_starts_with($k, 'L:') && ($o = wc_get_order(absint(substr($k, 2))))) {
                $list[] = $o;
            } elseif (str_starts_with($k, 'R:') && tli_mode() === 'hub') {
                // Invoices of other stores are issued (and numbered) by that store; fetch them for printing here.
                global $wpdb;
                $r = $wpdb->get_row($wpdb->prepare("SELECT store_id, remote_id, number FROM {$wpdb->prefix}tl_remote_orders WHERE id = %d", absint(substr($k, 2))), ARRAY_A);
                $st = $r ? tli_store($r['store_id']) : null;
                try {
                    if (!$st) {
                        throw new RuntimeException('store not found');
                    }
                    $res = tli_call($st['url'], $st['id'], $st['secret'], 'POST', 'invoice', ['remote_id' => $r['remote_id']]);
                    $extra .= wp_kses_post((string) ($res['html'] ?? ''));
                } catch (Throwable $e) {
                    $skipped[] = sprintf('Order #%s from another store: invoice could not be fetched (%s).', $r['number'] ?? '?', $e->getMessage());
                }
            }
        }
        if ($what === 'creditnote') {
            tli_credit_note_document($list);
        } else {
            tli_invoice_document($list, $skipped, $extra);
        }
        exit;
    }
    $orders = tli_print_orders($keys);
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    $title = $what === 'slips' ? 'Packing slips' : 'Pick list';
    echo '<!doctype html><html><head><meta charset="utf-8"><title>' . esc_html($title . ' · ' . get_bloginfo('name')) . '</title><style>' . tli_print_css() . '</style></head><body>';
    printf('<div class="bar no-print"><b>%s</b> · %d order%s <button onclick="window.print()">Print</button></div>', esc_html($title), count($orders), count($orders) === 1 ? '' : 's');
    if (!$orders) {
        echo '<p class="empty">No orders selected. Go back and tick the orders to print, or use "All orders to pack".</p>';
    } elseif ($what === 'slips') {
        tli_render_slips($orders);
    } else {
        tli_render_pick($orders);
    }
    echo '<script>if (location.hash !== "#noprint") setTimeout(() => window.print(), 300);</script></body></html>';
    exit;
});

function tli_render_pick(array $orders): void
{
    $items = [];
    foreach ($orders as $o) {
        foreach ($o['lines'] as $l) {
            $k = $l['sku'] !== '' ? $l['sku'] : $l['name'];
            $items[$k] ??= ['sku' => $l['sku'], 'name' => $l['name'], 'qty' => 0, 'orders' => []];
            $items[$k]['qty'] += (int) $l['qty'];
            $items[$k]['orders'][] = '#' . $o['number'] . ($o['store'] !== get_bloginfo('name') ? ' (' . $o['store'] . ')' : '') . ((int) $l['qty'] > 1 ? ' ×' . (int) $l['qty'] : '');
        }
    }
    ksort($items, SORT_NATURAL);
    printf('<header class="doc-head"><div><h1>Pick list</h1><p>%s · Delhi warehouse · %s</p></div><div class="mark">TL</div></header>', esc_html(get_bloginfo('name')), esc_html(wp_date('d M Y, H:i')));
    printf('<p class="sum"><b>%d</b> orders · <b>%d</b> different items · <b>%d</b> pieces in total</p>', count($orders), count($items), array_sum(array_column($items, 'qty')));
    echo '<table><thead><tr><th class="chk">✓</th><th>SKU</th><th>Product</th><th class="n">Qty</th><th>For orders</th></tr></thead><tbody>';
    foreach ($items as $i) {
        $pid = $i['sku'] !== '' ? wc_get_product_id_by_sku($i['sku']) : 0;
        $p = $pid ? wc_get_product($pid) : null;
        $stock = $p && $p->managing_stock() ? ' <span class="muted">(stock ' . (int) $p->get_stock_quantity() . ')</span>' : '';
        printf('<tr><td class="chk"><span class="box"></span></td><td class="mono">%s</td><td>%s%s</td><td class="n big">%d</td><td class="small">%s</td></tr>', esc_html($i['sku'] ?: '—'), esc_html($i['name']), $stock, $i['qty'], esc_html(implode(', ', $i['orders'])));
    }
    echo '</tbody></table><p class="foot">Picked by: ____________________ &nbsp; Checked by: ____________________</p>';
}

function tli_render_slips(array $orders): void
{
    $logo = get_stylesheet_directory_uri() . '/assets/img/email-logo.png';
    foreach ($orders as $o) {
        echo '<section class="slip">';
        printf('<header class="doc-head"><div><img class="logo" src="%s" alt=""><p class="muted">%s</p></div><div class="right"><h1>Packing slip</h1><p>Order <b>#%s</b><br>%s</p></div></header>',
            esc_url($logo), esc_html($o['store']), esc_html($o['number']), esc_html(wp_date('d M Y', (int) $o['date'])));
        printf('<div class="cols"><div><h2>Ship to</h2><p class="addr">%s</p>%s</div><div><h2>Ships from</h2><p class="addr">%s<br>Delhi warehouse, India</p>%s</div></div>',
            nl2br(esc_html($o['ship_to'] !== '' ? $o['ship_to'] : $o['customer'])), $o['phone'] !== '' ? '<p>Phone: ' . esc_html($o['phone']) . '</p>' : '',
            esc_html($o['store']), $o['shipping'] ? '<p>Service: ' . esc_html($o['shipping']) . '</p>' : '');
        echo '<table><thead><tr><th class="chk">✓</th><th>Item</th><th>SKU</th><th class="n">Qty</th></tr></thead><tbody>';
        foreach ($o['lines'] as $l) {
            printf('<tr><td class="chk"><span class="box"></span></td><td>%s</td><td class="mono">%s</td><td class="n big">%d</td></tr>', esc_html($l['name']), esc_html($l['sku']), (int) $l['qty']);
        }
        echo '</tbody></table>';
        if ($o['note'] !== '') {
            echo '<p class="note"><b>Customer note:</b> ' . esc_html($o['note']) . '</p>';
        }
        printf('<p class="foot">Thank you for shopping with %s. For returns or questions, see our Returns & Refunds page or contact us.</p></section>', esc_html($o['store']));
    }
}

function tli_print_css(): string
{
    return 'body{font:13px/1.45 -apple-system,"Segoe UI",Inter,Arial,sans-serif;color:#111;margin:0;padding:24px;background:#fff}
.bar{position:sticky;top:0;background:#0b1f4d;color:#fff;padding:10px 16px;margin:-24px -24px 20px;display:flex;gap:12px;align-items:center}
.bar button{margin-left:auto;background:#f2a900;border:0;padding:7px 16px;font-weight:600;border-radius:5px;cursor:pointer}
.doc-head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #111;padding-bottom:12px;margin-bottom:16px}
h1{font-size:20px;margin:0 0 4px}h2{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#666;margin:0 0 6px}
.mark{width:36px;height:36px;border-radius:8px;background:#0b1f4d;color:#fff;display:grid;place-items:center;font-weight:700;box-shadow:inset 0 -3px 0 #f2a900}
.right{text-align:right}.logo{height:46px;width:auto}.muted{color:#777}.small{font-size:11.5px;color:#444}.mono{font-family:Menlo,Consolas,monospace;font-size:12px}
.sum{margin:0 0 12px}table{width:100%;border-collapse:collapse;margin:8px 0 14px}th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#555;border-bottom:1.5px solid #111;padding:7px 6px}
td{border-bottom:1px solid #ddd;padding:9px 6px;vertical-align:top}.n{text-align:right}.big{font-size:16px;font-weight:700}.chk{width:28px}.box{display:inline-block;width:14px;height:14px;border:1.5px solid #111;border-radius:3px}
.cols{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:10px}.addr{margin:0 0 6px;font-size:14px}.note{background:#fdf4e3;padding:8px 10px;border-radius:5px}
.foot{margin-top:18px;font-size:11.5px;color:#555}.slip{page-break-after:always;padding-bottom:20px}.slip:last-child{page-break-after:auto}.empty{padding:40px;text-align:center;color:#666}
@media print{.no-print{display:none}body{padding:0}@page{margin:14mm}}';
}
