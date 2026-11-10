<?php
/**
 * GST tax invoices for orders sold on this site. Prices include GST; each line's taxable value and tax are worked out
 * from the order (WooCommerce's own tax lines when taxes are switched on, otherwise the product's GST rate class).
 * Same state as the business's GSTIN → CGST + SGST, other states → IGST, exports → IGST or zero-rated under LUT.
 * Invoice numbers are sequential per financial year (PREFIX/26-27/0001) and given once, when the order is paid.
 * Until the business details are filled in (Settings → GST invoice) only a DRAFT (not a tax invoice) can be printed.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/** Extra business details kept with the invoice details and shown on the store's pages. */
const TLI_STORE_FIELDS = ['hours', 'grievance', 'jurisdiction', 'dispatch', 'eta_in', 'eta_world', 'instagram'];

/**
 * [tl_store field="email" before="Email: " after="" fallback=""] — a business detail for policy/contact pages.
 * Prints nothing (or the fallback) when the detail is not filled in, so pages never show blanks.
 */
add_shortcode('tl_store', static function ($a): string {
    $a = shortcode_atts(['field' => '', 'before' => '', 'after' => '', 'fallback' => '', 'link' => ''], $a);
    $c = tli_invoice_conf();
    $v = trim((string) ($a['field'] === 'name' ? get_bloginfo('name') : ($c[$a['field']] ?? '')));
    if ($v === '') {
        return esc_html($a['fallback']);
    }
    $out = $a['field'] === 'address' ? nl2br(esc_html($v)) : esc_html($v);
    if ($a['field'] === 'email') {
        $out = '<a href="mailto:' . esc_attr(antispambot($v)) . '">' . esc_html(antispambot($v)) . '</a>';
    } elseif ($a['field'] === 'phone') {
        $out = '<a href="tel:' . esc_attr(preg_replace('/[^\d+]/', '', $v)) . '">' . esc_html($v) . '</a>';
    } elseif ($a['field'] === 'instagram' && wp_http_validate_url($v)) {
        $out = '<a href="' . esc_url($v) . '" rel="noopener" target="_blank">' . esc_html(preg_replace('#^https?://(www\.)?#', '', $v)) . '</a>';
    }
    return wp_kses_post($a['before']) . $out . wp_kses_post($a['after']);
});

/**
 * [tl_store_block type="operator|contact|grievance"] — composite blocks for the policy pages, built only from details
 * that are filled in (TL Commerce Hub → Settings), so nothing incomplete is ever shown to customers.
 */
add_shortcode('tl_store_block', static function ($a): string {
    $type = sanitize_key((shortcode_atts(['type' => 'contact'], $a))['type']);
    $c = tli_invoice_conf();
    $name = get_bloginfo('name');
    $sc = static fn (string $field): string => do_shortcode('[tl_store field="' . $field . '"]');
    if ($type === 'operator') {
        if ($c['legal_name'] === '') {
            return '<p>' . esc_html(sprintf('This website and the %s store are operated by %s.', $name, $name)) . '</p>';
        }
        $bits = esc_html($c['legal_name']) . ($c['gstin'] !== '' ? ' (GSTIN ' . esc_html($c['gstin']) . ')' : '') . ($c['address'] !== '' ? ', ' . esc_html(preg_replace('/\s*\R\s*/', ', ', $c['address'])) : '');
        return '<p>' . sprintf('This website and the %s store are operated by %s ("we", "us").', esc_html($name), $bits) . '</p>';
    }
    if ($type === 'grievance') {
        $who = $c['grievance'] !== '' ? 'Our Grievance Officer is <strong>' . esc_html($c['grievance']) . '</strong>' . ($c['email'] !== '' ? ', reachable at ' . $sc('email') : '') . '.' : 'For any complaint, write to us using the details on our Contact page.';
        return '<p>' . $who . ' We acknowledge every complaint within 48 hours and resolve it within one month of receiving it.</p>';
    }
    $rows = [];
    foreach (['email' => 'Email', 'phone' => 'Phone / WhatsApp', 'hours' => 'Hours', 'instagram' => 'Instagram', 'address' => 'Registered address', 'legal_name' => 'Business', 'gstin' => 'GSTIN'] as $k => $label) {
        if ($c[$k] !== '') {
            $rows[] = '<li><strong>' . esc_html($label) . ':</strong> ' . $sc($k) . '</li>';
        }
    }
    if ($c['email'] === '' && $c['phone'] === '') {
        array_unshift($rows, '<li>Our customer support email and phone number will be listed here before orders open.</li>');
    }
    $track = (int) get_option('tli_track_page');
    if ($track) {
        $rows[] = '<li><strong>Where is my order?</strong> <a href="' . esc_url(get_permalink($track)) . '">Track your order</a> with your order number and email.</li>';
    }
    return $rows ? '<ul class="tl-store-contact">' . implode('', $rows) . '</ul>' : '';
});

/** WooCommerce state code => GST state code. */
const TLI_GST_STATE_CODES = [
    'JK' => '01', 'HP' => '02', 'PB' => '03', 'CH' => '04', 'UK' => '05', 'HR' => '06', 'DL' => '07', 'RJ' => '08', 'UP' => '09', 'BR' => '10',
    'SK' => '11', 'AR' => '12', 'NL' => '13', 'MN' => '14', 'MZ' => '15', 'TR' => '16', 'ML' => '17', 'AS' => '18', 'WB' => '19', 'JH' => '20',
    'OR' => '21', 'CT' => '22', 'MP' => '23', 'GJ' => '24', 'DD' => '26', 'DN' => '26', 'MH' => '27', 'KA' => '29', 'GA' => '30', 'LD' => '31',
    'KL' => '32', 'TN' => '33', 'PY' => '34', 'AN' => '35', 'TS' => '36', 'AP' => '37', 'LA' => '38',
];

function tli_invoice_conf(): array
{
    $biz = (array) get_option('schermo_business', []); // filled by the client sheet importer, if present
    $c = (array) get_option('tli_invoice', []);
    $pick = static fn (string $k, string $d = ''): string => (string) (($c[$k] ?? '') !== '' ? $c[$k] : ($biz[$k] ?? $d));
    return ['legal_name' => $pick('legal_name'), 'gstin' => strtoupper($pick('gstin')), 'address' => $pick('address'), 'prefix' => $pick('prefix', 'INV'),
        'lut' => $pick('lut'), 'email' => $pick('email'), 'phone' => $pick('phone'),
        // Store details used on the policy and contact pages ([tl_store field="…"]).
        'hours' => $pick('hours'), 'grievance' => $pick('grievance'), 'jurisdiction' => $pick('jurisdiction'), 'dispatch' => $pick('dispatch'),
        'eta_in' => $pick('eta_in'), 'eta_world' => $pick('eta_world'), 'instagram' => $pick('instagram', (string) ($biz['social'] ?? ''))];
}

function tli_gstin_valid(string $g): bool
{
    return (bool) preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $g) && in_array(substr($g, 0, 2), TLI_GST_STATE_CODES, true);
}

/** What is still missing before real tax invoices can be issued. */
function tli_invoice_missing(): array
{
    $c = tli_invoice_conf();
    $m = [];
    if (trim($c['legal_name']) === '') {
        $m[] = 'legal business name';
    }
    if (!tli_gstin_valid($c['gstin'])) {
        $m[] = 'valid GSTIN';
    }
    if (trim($c['address']) === '') {
        $m[] = 'registered address';
    }
    return $m;
}

function tli_invoice_ready(): bool
{
    return !tli_invoice_missing();
}

/** Business state (WooCommerce code) from the GSTIN's first two digits. */
function tli_business_state(): string
{
    $code = substr(tli_invoice_conf()['gstin'], 0, 2);
    return (string) (array_search($code, TLI_GST_STATE_CODES, true) ?: '');
}

function tli_state_name(string $wc): string
{
    $states = WC()->countries->get_states('IN') ?: [];
    return (string) ($states[$wc] ?? $wc);
}

/** Financial year label (April–March), e.g. "26-27". */
function tli_fy(int $ts): string
{
    $y = (int) wp_date('Y', $ts);
    $start = (int) wp_date('n', $ts) >= 4 ? $y : $y - 1;
    return sprintf('%02d-%02d', $start % 100, ($start + 1) % 100);
}

/** The order's invoice number; given (sequentially, once) when the order is paid and the business details are set. */
function tli_invoice_number(WC_Order $o, bool $assign = true): string
{
    global $wpdb;
    $n = (string) $o->get_meta('_tl_invoice_no');
    if ($n !== '' || !$assign || !tli_invoice_ready() || !$o->is_paid()) {
        return $n;
    }
    if (!$wpdb->get_var("SELECT GET_LOCK('tli_invoice_no', 10)")) {
        return '';
    }
    try {
        $fresh = wc_get_order($o->get_id());
        $n = $fresh ? (string) $fresh->get_meta('_tl_invoice_no') : '';
        if ($n === '') {
            $now = time();
            $fy = tli_fy($now);
            $key = 'tli_invoice_seq_' . str_replace('-', '_', $fy);
            wp_cache_delete($key, 'options');
            $seq = (int) get_option($key, 0) + 1;
            update_option($key, $seq, false);
            $n = sprintf('%s/%s/%04d', tli_invoice_conf()['prefix'], $fy, $seq);
            $o->update_meta_data('_tl_invoice_no', $n);
            $o->update_meta_data('_tl_invoice_at', $now);
            $o->save_meta_data();
            $o->add_order_note('GST invoice ' . $n . ' issued.');
        }
    } finally {
        $wpdb->query("SELECT RELEASE_LOCK('tli_invoice_no')");
    }
    return $n;
}

add_action('woocommerce_order_status_changed', static function ($id, $from, $to, $order): void {
    if ($order instanceof WC_Order && in_array($to, ['processing', 'completed'], true)) {
        tli_invoice_number($order);
    }
}, 20, 4);

/** Customer-facing invoice link (only once the invoice has a number). */
function tli_invoice_link(WC_Order $o): string
{
    return tli_invoice_number($o, false) !== '' ? add_query_arg(['tl_invoice' => $o->get_id(), 'key' => $o->get_order_key()], home_url('/')) : '';
}

add_action('template_redirect', static function (): void {
    if (empty($_GET['tl_invoice'])) { // phpcs:ignore WordPress.Security.NonceVerification
        return;
    }
    $o = wc_get_order(absint($_GET['tl_invoice'])); // phpcs:ignore WordPress.Security.NonceVerification
    $key = sanitize_text_field(wp_unslash($_GET['key'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification
    if (!$o instanceof WC_Order || !hash_equals($o->get_order_key(), $key) || tli_invoice_number($o, false) === '') {
        wp_die('Invoice not found.', 'Invoice', ['response' => 404]);
    }
    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }
    if (!empty($_GET['cn'])) { // phpcs:ignore WordPress.Security.NonceVerification
        tli_credit_note_document([$o]);
    } else {
        tli_invoice_document([$o]);
    }
    exit;
});

add_action('woocommerce_order_details_after_order_table', static function ($o): void {
    if ($o instanceof WC_Order && ($link = tli_invoice_link($o))) {
        printf('<p><a class="button" href="%s" target="_blank" rel="noopener">Download GST invoice</a>%s</p>', esc_url($link),
            tli_credit_notes($o) ? ' <a class="button" href="' . esc_url(add_query_arg('cn', 1, $link)) . '" target="_blank" rel="noopener">Credit note</a>' : '');
    }
});

/** GST rate (%) of an order line, or null when unknown. */
function tli_line_rate(WC_Order_Item $item): ?float
{
    $r = 0.0;
    foreach (($item->get_taxes()['total'] ?? []) as $rid => $amt) {
        if ((float) $amt) {
            $r += (float) WC_Tax::get_rate_percent_value((int) $rid);
        }
    }
    if ($r > 0) {
        return $r;
    }
    $p = $item instanceof WC_Order_Item_Product ? $item->get_product() : null;
    if ($p && preg_match('/^gst-(\d+)(?:-(\d+))?$/', (string) $p->get_tax_class(), $m)) {
        return (float) ($m[1] . (isset($m[2]) ? '.' . $m[2] : ''));
    }
    return null;
}

function tli_line_hsn(WC_Order_Item_Product $item): string
{
    $p = $item->get_product();
    if (!$p) {
        return '';
    }
    $h = (string) $p->get_meta('_schermo_hsn');
    if ($h === '' && $p->get_parent_id() && ($parent = wc_get_product($p->get_parent_id()))) {
        $h = (string) $parent->get_meta('_schermo_hsn');
    }
    return $h;
}

/** Invoice lines and totals. */
function tli_invoice_data(WC_Order $o): array
{
    $c = tli_invoice_conf();
    $country = $o->get_shipping_country() ?: $o->get_billing_country();
    $state = $o->get_shipping_state() ?: $o->get_billing_state();
    $export = $country !== '' && $country !== 'IN';
    $zero = $export && $c['lut'] !== '';
    $intra = !$export && $state !== '' && $state === tli_business_state();
    $lines = [];
    $max = 0.0;
    // The amount the customer paid for each line (incl. GST, rounded like the order) is split into taxable value and
    // GST at the line's rate — WooCommerce's own tax amounts are rounded to whole rupees on this store.
    $add = static function (string $desc, string $hsn, int $qty, float $gross, ?float $rate) use (&$lines, $zero): void {
        if ($zero) {
            $rate = 0.0;
            $taxable = $gross;
        } elseif ($rate !== null) {
            $taxable = $gross / (1 + $rate / 100);
        } else {
            $taxable = $gross;
        }
        $taxable = round($taxable, 2);
        $lines[] = ['desc' => $desc, 'hsn' => $hsn, 'qty' => $qty, 'rate' => $rate, 'taxable' => $taxable, 'tax' => round($gross - $taxable, 2), 'gross' => round($gross, 2)];
    };
    foreach ($o->get_items() as $item) {
        $rate = tli_line_rate($item);
        $max = max($max, (float) $rate);
        $p = $item->get_product();
        $add($item->get_name() . ($p && $p->get_sku() ? ' (' . $p->get_sku() . ')' : ''), tli_line_hsn($item), (int) $item->get_quantity(), (float) $o->get_line_total($item, true, true), $rate);
    }
    // Delivery and other charges are part of the same supply and taxed at the goods' (highest) rate.
    foreach ($o->get_items(['shipping', 'fee']) as $item) {
        $gross = round((float) $item->get_total() + (float) $item->get_total_tax(), wc_get_price_decimals());
        if (abs($gross) < 0.005) {
            continue;
        }
        $rate = tli_line_rate($item) ?? ($max > 0 ? $max : null);
        $add($item->get_type() === 'shipping' ? 'Delivery charges' : $item->get_name(), '', 1, $gross, $rate);
    }
    $t = ['taxable' => 0.0, 'tax' => 0.0, 'gross' => 0.0];
    foreach ($lines as $l) {
        foreach ($t as $k => $v) {
            $t[$k] += $l[$k];
        }
    }
    return ['lines' => $lines, 'totals' => $t, 'intra' => $intra, 'export' => $export, 'zero' => $zero, 'state' => $state, 'country' => $country,
        'missing_rate' => (bool) array_filter($lines, static fn ($l) => $l['rate'] === null)];
}

/** Rupees in words, Indian numbering (lakh, crore). */
function tli_amount_words(float $amt): string
{
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $two = static fn (int $n): string => $n < 20 ? $ones[$n] : trim($tens[intdiv($n, 10)] . ' ' . $ones[$n % 10]);
    $three = static fn (int $n): string => trim(($n >= 100 ? $ones[intdiv($n, 100)] . ' Hundred ' : '') . $two($n % 100));
    $words = static function (int $n) use ($two, $three): string {
        if ($n === 0) {
            return 'Zero';
        }
        $parts = [];
        foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand']] as [$div, $name]) {
            if ($n >= $div) {
                $q = intdiv($n, $div);
                $parts[] = ($q >= 100 ? $three($q) : $two($q)) . ' ' . $name;
                $n %= $div;
            }
        }
        if ($n) {
            $parts[] = $three($n);
        }
        return implode(' ', $parts);
    };
    $rupees = (int) floor(round($amt, 2));
    $paise = (int) round((round($amt, 2) - $rupees) * 100);
    return 'Rupees ' . $words($rupees) . ($paise ? ' and ' . $words($paise) . ' Paise' : '') . ' Only';
}

/** Full HTML page with one invoice per order. */
function tli_invoice_document(array $orders, array $skipped = [], string $extra = ''): void
{
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>Tax invoice · ' . esc_html(get_bloginfo('name')) . '</title><style>' . tli_print_css() . tli_invoice_css() . '</style></head><body>';
    printf('<div class="bar no-print"><b>GST invoice</b> · %d order%s <button onclick="window.print()">Print / save as PDF</button></div>', count($orders), count($orders) === 1 ? '' : 's');
    if ($skipped) {
        echo '<p class="note no-print">' . esc_html(implode(' ', $skipped)) . '</p>';
    }
    foreach ($orders as $o) {
        tli_render_invoice($o);
    }
    echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput -- invoices from connected stores, passed through wp_kses_post
    if (!$orders && $extra === '') {
        echo '<p class="empty">No orders to invoice.</p>';
    }
    echo '</body></html>';
}

function tli_credit_note_document(array $orders): void
{
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>Credit note · ' . esc_html(get_bloginfo('name')) . '</title><style>' . tli_print_css() . tli_invoice_css() . '</style></head><body>';
    echo '<div class="bar no-print"><b>Credit notes</b><button onclick="window.print()">Print / save as PDF</button></div>';
    $any = false;
    foreach ($orders as $o) {
        foreach (tli_credit_notes($o) as $cn) {
            tli_render_invoice($o, $cn);
            $any = true;
        }
    }
    if (!$any) {
        echo '<p class="empty">No credit notes for these orders.</p>';
    }
    echo '</body></html>';
}

/** Invoice + credit notes of one order as an HTML fragment (sent to the hub for printing at the warehouse). */
function tli_invoice_fragment(WC_Order $o): string
{
    ob_start();
    tli_render_invoice($o);
    foreach (tli_credit_notes($o) as $cn) {
        tli_render_invoice($o, $cn);
    }
    return (string) ob_get_clean();
}

function tli_render_invoice(WC_Order $o, ?array $cn = null): void
{
    $c = tli_invoice_conf();
    $inv_no = tli_invoice_number($o, $cn === null);
    $no = $cn ? $cn['no'] : $inv_no;
    $draft = $no === '';
    $d = tli_invoice_data($o);
    $at = (int) $o->get_meta('_tl_invoice_at') ?: ($o->get_date_paid() ? $o->get_date_paid()->getTimestamp() : time());
    $inv_at = $at;
    if ($cn) {
        $d = array_merge($d, ['lines' => $cn['lines'], 'totals' => $cn['totals'], 'missing_rate' => false]);
        $at = $cn['at'];
    }
    $bill = wp_strip_all_tags(str_replace(['<br/>', '<br>'], "\n", (string) $o->get_formatted_billing_address()));
    $ship = wp_strip_all_tags(str_replace(['<br/>', '<br>'], "\n", (string) ($o->get_formatted_shipping_address() ?: $o->get_formatted_billing_address())));
    $pos = $d['export'] ? 'Outside India (' . (WC()->countries->get_countries()[$d['country']] ?? $d['country']) . ') — 96' : ($d['state'] !== '' ? tli_state_name($d['state']) . ' — ' . (TLI_GST_STATE_CODES[$d['state']] ?? '') : '—');
    echo '<section class="slip inv">';
    if ($draft) {
        $why = tli_invoice_missing() ? 'business details are missing (' . implode(', ', tli_invoice_missing()) . ') — fill them in TL Commerce Hub → Settings → GST invoice' : 'the order is not paid yet';
        echo '<div class="inv-draft">DRAFT — NOT A TAX INVOICE: ' . esc_html($why) . '.</div>';
    }
    if (!$cn && !$draft && ($cns = tli_credit_notes($o))) {
        echo '<p class="note no-print">Credit note' . (count($cns) > 1 ? 's' : '') . ' issued against this invoice: ' . esc_html(implode(', ', array_column($cns, 'no'))) . '.</p>';
    }
    printf('<header class="doc-head"><div><img class="logo" src="%s" alt=""><p><b>%s</b><br>%s<br>GSTIN: <b>%s</b>%s</p></div><div class="right"><h1>%s</h1><p>%s no. <b>%s</b><br>Date: %s<br>%sOrder #%s · %s</p></div></header>',
        esc_url(get_stylesheet_directory_uri() . '/assets/img/email-logo.png'), esc_html($c['legal_name'] ?: '[LEGAL BUSINESS NAME]'), nl2br(esc_html($c['address'] ?: '[REGISTERED ADDRESS]')),
        esc_html($c['gstin'] ?: '[GSTIN]'), ($c['email'] || $c['phone']) ? '<br>' . esc_html(trim($c['email'] . ' · ' . $c['phone'], ' ·')) : '',
        $cn ? 'Credit Note' : 'Tax Invoice', $cn ? 'Credit note' : 'Invoice', esc_html($draft ? '—' : $no), esc_html(wp_date('d M Y', $at)),
        $cn ? 'Against invoice <b>' . esc_html($inv_no) . '</b> of ' . esc_html(wp_date('d M Y', $inv_at)) . '<br>' : '', esc_html($o->get_order_number()), esc_html(wc_format_datetime($o->get_date_created(), 'd M Y')));
    printf('<div class="cols"><div><h2>Billed to</h2><p class="addr">%s</p>%s</div><div><h2>Shipped to</h2><p class="addr">%s</p><p>Place of supply: <b>%s</b><br>Reverse charge: No</p></div></div>',
        nl2br(esc_html($bill)), $o->get_billing_email() ? '<p>' . esc_html($o->get_billing_email()) . '</p>' : '', nl2br(esc_html($ship)), esc_html($pos));
    $split = $d['intra'];
    echo '<table class="inv-t"><thead><tr><th>#</th><th>Description</th><th>HSN</th><th class="n">Qty</th><th class="n">Taxable value</th>'
        . ($split ? '<th class="n">CGST</th><th class="n">SGST</th>' : '<th class="n">IGST</th>') . '<th class="n">Total</th></tr></thead><tbody>';
    $fmt = static fn (float $v): string => number_format($v, 2);
    $i = 0;
    $tax_cg = 0.0;
    foreach ($d['lines'] as $l) {
        $i++;
        $rate = $l['rate'] === null ? '?' : rtrim(rtrim(number_format($l['rate'], 2), '0'), '.');
        $half = round($l['tax'] / 2, 2);
        $tax_cg += $half;
        $taxcols = $split ? sprintf('<td class="n">%s<small>%s%%</small></td><td class="n">%s<small>%s%%</small></td>', $fmt($half), $l['rate'] === null ? '?' : rtrim(rtrim(number_format($l['rate'] / 2, 2), '0'), '.'), $fmt($l['tax'] - $half), $l['rate'] === null ? '?' : rtrim(rtrim(number_format($l['rate'] / 2, 2), '0'), '.'))
            : sprintf('<td class="n">%s<small>%s%%</small></td>', $fmt($l['tax']), $rate);
        printf('<tr><td>%d</td><td>%s</td><td class="mono">%s</td><td class="n">%s</td><td class="n">%s</td>%s<td class="n">%s</td></tr>', $i, esc_html($l['desc']), esc_html($l['hsn'] ?: '—'), $l['qty'] === null ? '—' : (int) $l['qty'], $fmt($l['taxable']), $taxcols, $fmt($l['gross']));
    }
    $t = $d['totals'];
    printf('</tbody><tfoot><tr><td></td><td colspan="3"><b>Total</b></td><td class="n"><b>%s</b></td>%s<td class="n"><b>%s</b></td></tr></tfoot></table>', $fmt($t['taxable']),
        $split ? sprintf('<td class="n"><b>%s</b></td><td class="n"><b>%s</b></td>', $fmt($tax_cg), $fmt($t['tax'] - $tax_cg)) : sprintf('<td class="n"><b>%s</b></td>', $fmt($t['tax'])), $fmt($t['gross']));
    printf('<div class="inv-sum"><p>Amount in words: <b>%s</b></p><table><tr><td>Taxable value</td><td class="n">₹%s</td></tr><tr><td>GST</td><td class="n">₹%s</td></tr><tr class="g"><td>%s</td><td class="n">₹%s</td></tr><tr><td>%s</td><td class="n">%s</td></tr></table></div>',
        esc_html(tli_amount_words($t['gross'])), $fmt($t['taxable']), $fmt($t['tax']), $cn ? 'Credit note total' : 'Invoice total', $fmt($t['gross']), $cn ? 'Reason' : 'Paid via', esc_html($cn ? $cn['reason'] : ($o->get_payment_method_title() ?: '—')));
    if ($d['zero']) {
        echo '<p class="note">Supply meant for export under LUT (' . esc_html($c['lut']) . ') without payment of IGST.</p>';
    }
    if ($d['missing_rate']) {
        echo '<p class="note">GST rate missing for a line marked "?" — set the product\'s GST rate (client sheet "GST rate" column).</p>';
    }
    if (!$cn && abs($t['gross'] - (float) $o->get_total()) > 1.0) {
        echo '<p class="note no-print">Check: invoice total differs from the order total (₹' . esc_html($fmt((float) $o->get_total())) . ').</p>';
    }
    printf('<div class="inv-sign"><div class="muted">Prices include GST. This is a computer-generated %s.</div><div>For %s<br><br><br>Authorised signatory</div></div></section>', $cn ? 'credit note' : 'invoice', esc_html($c['legal_name'] ?: get_bloginfo('name')));
}

function tli_invoice_css(): string
{
    return '.inv{font-size:12.5px}.inv .doc-head p{margin:6px 0 0;line-height:1.5}.inv-draft{background:#fdeceb;color:#912018;border:1.5px dashed #b42318;padding:8px 12px;border-radius:6px;margin:0 0 12px;font-weight:700}
.inv-t td small{display:block;color:#777;font-size:10.5px}.inv-t tfoot td{border-top:1.5px solid #111;border-bottom:0}.inv-sum{display:flex;justify-content:space-between;gap:24px;align-items:flex-start;margin-top:6px}
.inv-sum table{width:auto;min-width:260px;margin:0}.inv-sum td{padding:5px 6px}.inv-sum tr.g td{font-weight:700;font-size:14px;border-top:1.5px solid #111}
.inv-sign{display:flex;justify-content:space-between;align-items:flex-end;margin-top:28px;text-align:right}.inv-sign .muted{text-align:left;font-size:11px}';
}

/* ── Credit notes ─────────────────────────────────────────────────────── */
/**
 * A credit note is issued (own series per financial year, PREFIXC/2627/0001) for every refund on an invoiced order,
 * and for an invoiced order cancelled without a refund. Refunds of specific items credit those items; a refund of an
 * amount only credits every invoice line in proportion.
 */
function tli_next_cn_number(): string
{
    global $wpdb;
    if (!$wpdb->get_var("SELECT GET_LOCK('tli_invoice_no', 10)")) {
        return '';
    }
    try {
        $fy = tli_fy(time());
        $key = 'tli_cn_seq_' . str_replace('-', '_', $fy);
        wp_cache_delete($key, 'options');
        $seq = (int) get_option($key, 0) + 1;
        update_option($key, $seq, false);
        return sprintf('%sC/%s/%04d', tli_invoice_conf()['prefix'], str_replace('-', '', $fy), $seq);
    } finally {
        $wpdb->query("SELECT RELEASE_LOCK('tli_invoice_no')");
    }
}

add_action('woocommerce_order_refunded', static function ($order_id, $refund_id): void {
    $o = wc_get_order($order_id);
    $r = wc_get_order($refund_id);
    if (!$o instanceof WC_Order || !$r instanceof WC_Order_Refund || tli_invoice_number($o, false) === '' || $o->get_meta('_tl_cn_cancel_no') || $r->get_meta('_tl_cn_no')) {
        return;
    }
    if ($n = tli_next_cn_number()) {
        $r->update_meta_data('_tl_cn_no', $n);
        $r->update_meta_data('_tl_cn_at', time());
        $r->save_meta_data();
        $o->add_order_note('Credit note ' . $n . ' issued for the refund of ' . wp_strip_all_tags(wc_price((float) $r->get_amount())) . '.');
    }
}, 10, 2);

add_action('woocommerce_order_status_cancelled', static function ($id, $o = null): void {
    $o = $o instanceof WC_Order ? $o : wc_get_order($id);
    if (!$o || tli_invoice_number($o, false) === '' || $o->get_meta('_tl_cn_cancel_no') || $o->get_refunds()) {
        return;
    }
    if ($n = tli_next_cn_number()) {
        $o->update_meta_data('_tl_cn_cancel_no', $n);
        $o->update_meta_data('_tl_cn_cancel_at', time());
        $o->save_meta_data();
        $o->add_order_note('Order cancelled after invoicing: credit note ' . $n . ' issued for the full invoice.');
    }
}, 10, 2);

/** Every credit note of an order, oldest first, with its lines. */
function tli_credit_notes(WC_Order $o): array
{
    $inv = tli_invoice_data($o);
    $sum = static function (array $lines): array {
        $t = ['taxable' => 0.0, 'tax' => 0.0, 'gross' => 0.0];
        foreach ($lines as $l) {
            foreach ($t as $k => $v) {
                $t[$k] += $l[$k];
            }
        }
        return $t;
    };
    $scale = static function (float $amount) use ($inv): array {
        $total = (float) $inv['totals']['gross'];
        $f = $total > 0 ? min(1.0, $amount / $total) : 0.0;
        $out = [];
        foreach ($inv['lines'] as $l) {
            $g = round($l['gross'] * $f, 2);
            $tx = round($l['taxable'] * $f, 2);
            $out[] = ['desc' => $l['desc'], 'hsn' => $l['hsn'], 'qty' => $f >= 0.999 ? $l['qty'] : null, 'rate' => $l['rate'], 'taxable' => $tx, 'tax' => round($g - $tx, 2), 'gross' => $g];
        }
        // Rounding remainder goes on the largest line so the note adds up to exactly the refunded amount.
        $diff = round(min($amount, $total) - array_sum(array_column($out, 'gross')), 2);
        if ($out && abs($diff) > 0) {
            $k = array_search(max(array_column($out, 'gross')), array_column($out, 'gross'), true);
            $out[$k]['gross'] = round($out[$k]['gross'] + $diff, 2);
            $out[$k]['tax'] = round($out[$k]['gross'] - $out[$k]['taxable'], 2);
        }
        return $out;
    };
    $notes = [];
    if ($n = (string) $o->get_meta('_tl_cn_cancel_no')) {
        $lines = $scale((float) $inv['totals']['gross']);
        $notes[] = ['no' => $n, 'at' => (int) $o->get_meta('_tl_cn_cancel_at'), 'reason' => 'Order cancelled', 'lines' => $lines, 'totals' => $sum($lines)];
    }
    foreach ($o->get_refunds() as $r) {
        if (!($n = (string) $r->get_meta('_tl_cn_no'))) {
            continue;
        }
        $lines = [];
        $itemised = 0.0;
        foreach ($r->get_items(['line_item', 'shipping', 'fee']) as $ri) {
            $gross = round(abs((float) $ri->get_total() + (float) $ri->get_total_tax()), wc_get_price_decimals());
            if ($gross < 0.005) {
                continue;
            }
            $orig = $o->get_item((int) $ri->get_meta('_refunded_item_id'));
            $rate = $orig ? tli_line_rate($orig) : null;
            if ($rate === null && $ri->get_type() !== 'line_item') {
                $rate = max(array_map(static fn ($l) => (float) $l['rate'], $inv['lines']) ?: [0]) ?: null;
            }
            $taxable = $inv['zero'] || $rate === null ? $gross : round($gross / (1 + $rate / 100), 2);
            $hsn = $orig instanceof WC_Order_Item_Product ? tli_line_hsn($orig) : '';
            $lines[] = ['desc' => $ri->get_type() === 'shipping' ? 'Delivery charges' : $ri->get_name(), 'hsn' => $hsn, 'qty' => $ri->get_type() === 'line_item' ? abs((int) $ri->get_quantity()) : 1,
                'rate' => $inv['zero'] ? 0.0 : $rate, 'taxable' => $taxable, 'tax' => round($gross - $taxable, 2), 'gross' => $gross];
            $itemised += $gross;
        }
        $amount = (float) $r->get_amount();
        if (!$lines || abs($itemised - $amount) > 1.0) {
            $lines = $scale($amount); // refund of an amount (e.g. from the payment gateway): credit every line in proportion
        }
        $notes[] = ['no' => $n, 'at' => (int) $r->get_meta('_tl_cn_at') ?: time(), 'reason' => $r->get_reason() ?: 'Refund', 'lines' => $lines, 'totals' => $sum($lines)];
    }
    usort($notes, static fn ($a, $b) => strnatcmp($a['no'], $b['no']));
    return $notes;
}

/* ── Settings: GST invoice + couriers ─────────────────────────────────── */
add_action('admin_post_tli_invoice_settings', static function (): void {
    if (!current_user_can('tl_manage')) {
        wp_die('Not allowed.', 403);
    }
    check_admin_referer('tli_invoice_settings');
    $gstin = strtoupper(preg_replace('/\s+/', '', sanitize_text_field(wp_unslash($_POST['gstin'] ?? ''))));
    $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', sanitize_text_field(wp_unslash($_POST['prefix'] ?? 'INV')))) ?: 'INV';
    update_option('tli_invoice', [
        'legal_name' => sanitize_text_field(wp_unslash($_POST['legal_name'] ?? '')), 'gstin' => $gstin, 'address' => sanitize_textarea_field(wp_unslash($_POST['address'] ?? '')),
        'prefix' => substr($prefix, 0, 5), 'lut' => sanitize_text_field(wp_unslash($_POST['lut'] ?? '')),
        'email' => sanitize_email(wp_unslash($_POST['email'] ?? '')), 'phone' => sanitize_text_field(wp_unslash($_POST['phone'] ?? '')),
    ] + array_map(static fn ($k) => sanitize_text_field(wp_unslash($_POST[$k] ?? '')), array_combine(TLI_STORE_FIELDS, TLI_STORE_FIELDS)), false);
    if (function_exists('schermo_cache_clear')) {
        schermo_cache_clear(); // policy and contact pages show these details
    }
    update_option('tli_couriers', sanitize_textarea_field(wp_unslash($_POST['couriers'] ?? '')) ?: TLI_COURIERS_DEFAULT);
    $msg = $gstin !== '' && !tli_gstin_valid($gstin) ? 'Saved, but the GSTIN does not look valid (15 characters, e.g. 07ABCDE1234F1Z5) — invoices stay as drafts.' : 'Saved.';
    wp_safe_redirect(add_query_arg(['tli_msg' => rawurlencode($msg), 'tli_ok' => $msg === 'Saved.' ? '1' : '0'], admin_url('admin.php?page=tl-settings')));
    exit;
});

function tli_settings_invoice_card(): void
{
    $c = tli_invoice_conf();
    $missing = tli_invoice_missing();
    echo '<div class="tli-card"><h2>Business details &amp; GST invoice</h2><p class="muted">Printed on every tax invoice and shown on the Contact, Terms, Privacy, Shipping and Returns pages. A line whose detail is empty is simply left out of those pages. Invoices are numbered when an order is paid; until the GST details are complete only drafts can be printed.</p>';
    echo $missing ? '<div class="tli-flash is-bad">Missing: ' . esc_html(implode(', ', $missing)) . '.</div>' : '<div class="tli-flash is-ok">Ready — paid orders get a GST invoice number. Business state: ' . esc_html(tli_state_name(tli_business_state())) . '.</div>';
    printf('<form method="post" action="%s"><input type="hidden" name="action" value="tli_invoice_settings">%s<div class="tli-po-head">', esc_url(admin_url('admin-post.php')), wp_nonce_field('tli_invoice_settings', '_wpnonce', true, false));
    printf('<label class="tli-field"><span>Legal business name</span><input class="tli-input" name="legal_name" value="%s" placeholder="As on the GST certificate"></label>', esc_attr($c['legal_name']));
    printf('<label class="tli-field"><span>GSTIN</span><input class="tli-input" name="gstin" value="%s" maxlength="15" placeholder="07ABCDE1234F1Z5" style="text-transform:uppercase"></label>', esc_attr($c['gstin']));
    printf('<label class="tli-field"><span>Invoice prefix (max 5)</span><input class="tli-input" name="prefix" value="%s" maxlength="5"></label></div>', esc_attr($c['prefix']));
    printf('<label class="tli-field"><span>Registered address</span><textarea class="tli-input tli-textarea" name="address" rows="3">%s</textarea></label>', esc_textarea($c['address']));
    printf('<div class="tli-po-head"><label class="tli-field"><span>Support email on invoice</span><input class="tli-input" type="email" name="email" value="%s"></label><label class="tli-field"><span>Support phone on invoice</span><input class="tli-input" name="phone" value="%s"></label><label class="tli-field"><span>LUT reference (exports, optional)</span><input class="tli-input" name="lut" value="%s" placeholder="Only if you export under LUT"></label></div>',
        esc_attr($c['email']), esc_attr($c['phone']), esc_attr($c['lut']));
    $f = static fn (string $k, string $label, string $ph) => sprintf('<label class="tli-field"><span>%s</span><input class="tli-input" name="%s" value="%s" placeholder="%s"></label>', esc_html($label), esc_attr($k), esc_attr($c[$k]), esc_attr($ph));
    echo '<h2 style="margin-top:20px">Shown on the website pages</h2><div class="tli-po-head">' . $f('hours', 'Support hours', 'Mon–Sat, 10 am – 6 pm IST') . $f('grievance', 'Grievance officer (name)', 'Required by India\'s e-commerce rules') . $f('jurisdiction', 'Courts at (city)', 'e.g. New Delhi') . '</div>';
    echo '<div class="tli-po-head">' . $f('dispatch', 'Dispatch time', 'e.g. 2–3 working days') . $f('eta_in', 'Delivery time in India', 'e.g. 3–7 working days') . $f('eta_world', 'Delivery time abroad', 'e.g. 7–14 working days') . '</div>';
    echo '<div class="tli-po-head">' . $f('instagram', 'Instagram link', 'https://instagram.com/…') . '</div>';
    echo '<p class="muted small">Numbers look like <code>' . esc_html($c['prefix'] . '/' . tli_fy(time()) . '/0001') . '</code> and restart each financial year (April). GST rate per product comes from its tax class (set by the client sheet\'s "GST rate" column); HSN from the sheet\'s "HSN" column. Have your CA check the first invoice.</p>';
    printf('<h2 style="margin-top:24px">Couriers</h2><p class="muted">One per line. Add a tracking link with <code>{awb}</code> where the tracking number goes, e.g. <code>Courier name | https://courier-site/track/{awb}</code> — copy the exact link format from your courier. Without a link the customer still gets the courier name and AWB.</p><textarea class="tli-input tli-textarea" name="couriers" rows="6">%s</textarea>',
        esc_textarea((string) get_option('tli_couriers', TLI_COURIERS_DEFAULT)));
    echo '<p><button class="tli-btn primary">Save</button></p></form></div>';
}
