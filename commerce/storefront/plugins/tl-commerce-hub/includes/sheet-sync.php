<?php
/**
 * Google Sheet sync (hub). One live Google Sheet that the team and the client edit: products and prices, stock per
 * size per warehouse, and the store's business details. The sheet's Apps Script (commerce/storefront/google-sheet/
 * Code.gs) pulls the current data from here and pushes edited rows back, every few minutes and on demand.
 *
 * Requests are signed like the hub ↔ storefront calls: HMAC-SHA256 over timestamp, method, path and body with the
 * key from TL Commerce Hub → Settings → Google Sheet sync. Pushes change only what differs from the site; stock
 * changes go through the normal ledger (reason "Stocktake", note "Google Sheet"); every push is logged.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

const TLI_SHEET_STORE_KEYS = [
    'legal_name' => 'Legal business name', 'gstin' => 'GSTIN', 'address' => 'Registered address', 'email' => 'Customer support email',
    'phone' => 'Customer support phone / WhatsApp', 'hours' => 'Support hours', 'grievance' => 'Grievance officer (name)',
    'jurisdiction' => 'Courts at (city)', 'dispatch' => 'Default dispatch time', 'eta_in' => 'Delivery time in India',
    'eta_world' => 'Delivery time outside India', 'instagram' => 'Instagram link', 'prefix' => 'Invoice prefix (max 5)', 'lut' => 'LUT reference (exports)',
];

function tli_sheet_key(): string
{
    return (string) get_option('tli_sheet_key', '');
}

add_action('rest_api_init', static function (): void {
    if (tli_mode() !== 'hub') {
        return;
    }
    $auth = static function (WP_REST_Request $r) {
        $key = tli_sheet_key();
        return $key !== '' && (string) $r->get_header('x_tl_store') === 'sheet' && tli_verify($r, $key)
            ? true : new WP_Error('tl_auth', 'Sheet key invalid — copy it again from TL Commerce Hub → Settings → Google Sheet sync.', ['status' => 401]);
    };
    register_rest_route('tlhub/v1', '/sheet/pull', ['methods' => 'POST', 'permission_callback' => $auth, 'callback' => static fn () => tli_sheet_pull()]);
    register_rest_route('tlhub/v1', '/sheet/push', ['methods' => 'POST', 'permission_callback' => $auth, 'callback' => static function (WP_REST_Request $r) {
        $out = tli_sheet_push($r->get_json_params() ?: []);
        update_option('tli_sheet_last', ['at' => time(), 'applied' => $out['applied'], 'errors' => count($out['errors'])], false);
        return $out;
    }]);
});

/** GST rate (number) of a product, from its tax class "gst-N". */
function tli_sheet_gst(WC_Product $p): string
{
    return preg_match('/^gst-(\d+)(?:-(\d+))?$/', (string) $p->get_tax_class(), $m) ? $m[1] . (isset($m[2]) ? '.' . $m[2] : '') : '';
}

/** Everything the sheet shows. */
function tli_sheet_pull(): array
{
    $whs = tli_mode() === 'hub' && function_exists('tli_warehouses') ? tli_warehouses() : [];
    $codes = array_column($whs, 'code', 'id');
    $avail = function_exists('schermo_available_ids') ? array_flip(schermo_available_ids()) : [];
    $products = [];
    $stock = [];
    foreach (wc_get_products(['limit' => -1, 'status' => ['publish', 'draft', 'private'], 'orderby' => 'title', 'order' => 'ASC']) as $p) {
        if ($p->get_sku() === '') {
            continue;
        }
        $cats = wp_get_post_terms($p->get_id(), 'product_cat');
        $coll = $type = [];
        foreach (is_wp_error($cats) ? [] : $cats as $t) {
            if (in_array($t->slug, ['schermo-core', 'schermo-atelier', 'milan-icons', 'glasgow-cosmic'], true)) {
                $coll[] = $t->name;
            } elseif (!in_array($t->slug, ['schermo-sports', 'clothing', 'accessories'], true)) {
                $type[] = $t->name;
            }
        }
        $var = $p->is_type('variable');
        $reg = $var ? (string) $p->get_variation_regular_price('min') : (string) $p->get_regular_price();
        $now = $var ? (string) $p->get_variation_price('min') : (string) $p->get_price();
        $units = $var ? array_filter(array_map('wc_get_product', $p->get_children())) : [$p];
        $total = null;
        foreach ($units as $u) {
            $q = $u->managing_stock() ? (int) $u->get_stock_quantity() : null;
            $total = $q === null ? $total : (int) $total + $q;
            $split = $q !== null && $whs ? tli_wh_qty($u->get_id()) : [];
            $row = ['sku' => (string) $u->get_sku(), 'product_sku' => $p->get_sku(), 'name' => $p->get_name(), 'size' => $u->is_type('variation') ? strtoupper((string) $u->get_attribute('pa_size')) : 'One size', 'total' => $q, 'wh' => []];
            foreach ($whs as $w) {
                $row['wh'][$w['code']] = $q === null ? null : (int) ($split[(int) $w['id']] ?? 0);
            }
            $stock[] = $row;
        }
        $wid = (int) $p->get_meta('_tl_warehouse');
        $products[] = [
            'sku' => $p->get_sku(), 'photo' => (string) wp_get_attachment_image_url((int) $p->get_image_id(), 'woocommerce_thumbnail'), 'url' => $p->get_permalink(),
            'name' => $p->get_name(), 'collection' => implode(', ', $coll), 'type' => implode(', ', $type), 'status' => $p->get_status() === 'publish' ? 'Show' : 'Hide',
            'mrp' => $reg, 'price' => $now, 'short' => wp_strip_all_tags($p->get_short_description()), 'description' => wp_strip_all_tags($p->get_description()),
            'hsn' => (string) $p->get_meta('_schermo_hsn'), 'gst' => tli_sheet_gst($p), 'weight' => (string) $p->get_weight(),
            'ships_from' => $wid && isset($codes[$wid]) ? $codes[$wid] : '', 'dispatch' => (string) $p->get_meta('_tl_dispatch'),
            'stock' => $total, 'orderable' => isset($avail[$p->get_id()]) ? 'Yes' : 'No',
        ];
    }
    $conf = function_exists('tli_invoice_conf') ? tli_invoice_conf() : [];
    $store = [];
    foreach (TLI_SHEET_STORE_KEYS as $k => $label) {
        $store[] = ['key' => $k, 'label' => $label, 'value' => (string) ($conf[$k] ?? '')];
    }
    return ['ok' => true, 'site' => get_bloginfo('name'), 'at' => gmdate('c'), 'weight_unit' => get_option('woocommerce_weight_unit', 'kg'),
        'warehouses' => array_map(static fn ($w) => ['code' => $w['code'], 'name' => $w['name']], $whs), 'products' => $products, 'stock' => $stock, 'store' => $store];
}

/** A GST tax class for a rate, created (with an India rate) when missing — as the client sheet import does. */
function tli_sheet_gst_class(float $rate): string
{
    $label = 'GST ' . rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.') . '%';
    $slug = sanitize_title($label);
    if (!in_array($slug, WC_Tax::get_tax_class_slugs(), true)) {
        WC_Tax::create_tax_class($label, $slug);
    }
    if (!array_filter(WC_Tax::get_rates_for_tax_class($slug), static fn ($r) => $r->tax_rate_country === 'IN')) {
        WC_Tax::_insert_tax_rate(['tax_rate_country' => 'IN', 'tax_rate' => (string) $rate, 'tax_rate_name' => 'GST', 'tax_rate_priority' => 1, 'tax_rate_compound' => 0, 'tax_rate_shipping' => 0, 'tax_rate_order' => 0, 'tax_rate_class' => $slug]);
    }
    return $slug;
}

/** Applies edited rows. Only values that differ from the site are changed. */
function tli_sheet_push(array $d): array
{
    $applied = 0;
    $errors = [];
    $num = static fn ($v): ?string => $v === '' || $v === null ? '' : (is_numeric(str_replace([',', '₹', ' '], '', (string) $v)) ? (string) (0 + str_replace([',', '₹', ' '], '', (string) $v)) : null);
    $wh_by_code = [];
    foreach (function_exists('tli_warehouses') ? tli_warehouses() : [] as $w) {
        $wh_by_code[strtoupper($w['code'])] = (int) $w['id'];
    }
    foreach ((array) ($d['products'] ?? []) as $row) {
        $sku = sanitize_text_field((string) ($row['sku'] ?? ''));
        $id = $sku !== '' ? wc_get_product_id_by_sku($sku) : 0;
        $p = $id ? wc_get_product($id) : null;
        if (!$p || $p->is_type('variation')) {
            $errors[] = ['sku' => $sku, 'error' => 'Unknown product SKU'];
            continue;
        }
        $c = 0;
        try {
            if (isset($row['name']) && ($v = sanitize_text_field((string) $row['name'])) !== '' && $v !== $p->get_name()) {
                $p->set_name($v);
                $c++;
            }
            if (isset($row['status'])) {
                $st = strtolower(trim((string) $row['status'])) === 'hide' ? 'draft' : 'publish';
                if ($st !== $p->get_status()) {
                    $p->set_status($st);
                    $c++;
                }
            }
            foreach (['short' => 'short_description', 'description' => 'description'] as $k => $prop) {
                if (isset($row[$k]) && trim((string) $row[$k]) !== wp_strip_all_tags((string) $p->{"get_$prop"}())) {
                    $p->{"set_$prop"}(wp_kses_post((string) $row[$k]));
                    $c++;
                }
            }
            if (isset($row['hsn']) && ($v = sanitize_text_field((string) $row['hsn'])) !== (string) $p->get_meta('_schermo_hsn')) {
                $p->update_meta_data('_schermo_hsn', $v);
                $c++;
            }
            if (isset($row['dispatch']) && ($v = sanitize_text_field((string) $row['dispatch'])) !== (string) $p->get_meta('_tl_dispatch')) {
                $p->update_meta_data('_tl_dispatch', $v);
                $c++;
            }
            if (isset($row['ships_from'])) {
                $code = strtoupper(trim((string) $row['ships_from']));
                $wid = $code === '' ? '' : ($wh_by_code[$code] ?? null);
                if ($wid === null) {
                    throw new InvalidArgumentException("Ships from: no warehouse with code {$code}");
                }
                if ((string) $wid !== (string) $p->get_meta('_tl_warehouse')) {
                    $p->update_meta_data('_tl_warehouse', $wid);
                    $c++;
                }
            }
            if (isset($row['weight'])) {
                $w = $num($row['weight']);
                if ($w === null) {
                    throw new InvalidArgumentException('Weight must be a number');
                }
                if ($w !== (string) $p->get_weight()) {
                    $p->set_weight($w);
                    $c++;
                }
            }
            if (isset($row['gst'])) {
                $g = $num(str_replace('%', '', (string) $row['gst']));
                if ($g === null) {
                    throw new InvalidArgumentException('GST must be a number like 5 or 18');
                }
                $class = $g === '' || (float) $g <= 0 ? '' : tli_sheet_gst_class((float) $g);
                if ($class !== (string) $p->get_tax_class()) {
                    $p->set_tax_class($class);
                    $p->set_tax_status('taxable');
                    $c++;
                    if ($class !== '' && get_option('woocommerce_calc_taxes') !== 'yes') {
                        update_option('woocommerce_calc_taxes', 'yes');
                        update_option('woocommerce_prices_include_tax', 'yes');
                    }
                }
            }
            $p->save();
            // Prices: MRP = regular price; a lower selling price is the sale price. Applied to every size.
            if (isset($row['mrp']) || isset($row['price'])) {
                $mrp = $num($row['mrp'] ?? '');
                $price = $num($row['price'] ?? '');
                if ($mrp === null || $price === null) {
                    throw new InvalidArgumentException('MRP and selling price must be numbers');
                }
                if ($price === '' && $mrp !== '') {
                    $price = $mrp;
                }
                $regular = $mrp !== '' && (float) $mrp > (float) $price ? $mrp : $price;
                $sale = $mrp !== '' && (float) $mrp > (float) $price ? $price : '';
                $units = $p->is_type('variable') ? array_filter(array_map('wc_get_product', $p->get_children())) : [$p];
                $changed = false;
                foreach ($units as $u) {
                    if ((string) $u->get_regular_price() !== (string) $regular || (string) $u->get_sale_price() !== (string) $sale) {
                        $u->set_regular_price((string) $regular);
                        $u->set_sale_price((string) $sale);
                        $u->save();
                        $changed = true;
                    }
                }
                if ($changed) {
                    if ($p->is_type('variable')) {
                        WC_Product_Variable::sync($p->get_id());
                    }
                    wc_delete_product_transients($p->get_id());
                    $c++;
                }
            }
            $applied += $c;
        } catch (Throwable $e) {
            $errors[] = ['sku' => $sku, 'error' => $e->getMessage()];
        }
    }
    foreach ((array) ($d['stock'] ?? []) as $row) {
        $sku = sanitize_text_field((string) ($row['sku'] ?? ''));
        $id = $sku !== '' ? wc_get_product_id_by_sku($sku) : 0;
        $u = $id ? wc_get_product($id) : null;
        if (!$u || $u->is_type('variable')) {
            $errors[] = ['sku' => $sku, 'error' => 'Unknown size SKU'];
            continue;
        }
        try {
            $have = function_exists('tli_wh_qty') && $u->managing_stock() ? tli_wh_qty($u->get_id()) : [];
            foreach ((array) ($row['wh'] ?? []) as $code => $val) {
                if ($val === '' || $val === null) {
                    continue; // empty = leave as it is (a size not counted yet stays uncounted)
                }
                if (!is_numeric($val) || (int) $val < 0 || (string) (int) $val !== (string) (0 + $val)) {
                    throw new InvalidArgumentException("Stock for {$code} must be a whole number");
                }
                $wid = $wh_by_code[strtoupper((string) $code)] ?? null;
                if (!$wid) {
                    throw new InvalidArgumentException("No warehouse with code {$code}");
                }
                if (!$u->managing_stock() || (int) ($have[$wid] ?? 0) !== (int) $val) {
                    tli_adjust($u->get_id(), 'count', (int) $val, 'Google Sheet', null, $wid);
                    $u = wc_get_product($u->get_id());
                    $have = tli_wh_qty($u->get_id());
                    $applied++;
                }
            }
        } catch (Throwable $e) {
            $errors[] = ['sku' => $sku, 'error' => $e->getMessage()];
        }
    }
    if (!empty($d['store']) && is_array($d['store'])) {
        $cur = (array) get_option('tli_invoice', []);
        $new = $cur;
        foreach ($d['store'] as $k => $v) {
            if (!isset(TLI_SHEET_STORE_KEYS[$k])) {
                continue;
            }
            $v = $k === 'address' ? sanitize_textarea_field((string) $v) : ($k === 'email' ? sanitize_email((string) $v) : sanitize_text_field((string) $v));
            if ($k === 'gstin') {
                $v = strtoupper(preg_replace('/\s+/', '', $v));
            }
            if ($k === 'prefix') {
                $v = substr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $v)), 0, 5);
            }
            if ((string) ($cur[$k] ?? '') !== $v) {
                $new[$k] = $v;
                $applied++;
            }
        }
        if ($new !== $cur) {
            update_option('tli_invoice', $new, false);
            if (function_exists('schermo_cache_clear')) {
                schermo_cache_clear();
            }
        }
    }
    if ($applied && function_exists('schermo_cache_clear')) {
        schermo_cache_clear();
    }
    return ['ok' => true, 'applied' => $applied, 'errors' => $errors];
}

// ---- Settings card: key and status
add_action('admin_post_tli_sheet_key', static function (): void {
    if (!current_user_can('tl_manage')) {
        wp_die('Not allowed.', 403);
    }
    check_admin_referer('tli_sheet_key');
    update_option('tli_sheet_key', bin2hex(random_bytes(24)), false);
    wp_safe_redirect(add_query_arg(['tli_msg' => rawurlencode('New sheet key created. Paste it into the sheet (Schermo → Set up). The old key no longer works.'), 'tli_ok' => '1'], admin_url('admin.php?page=tl-settings#tli-sheet')));
    exit;
});

function tli_settings_sheet_card(): void
{
    if (tli_mode() !== 'hub') {
        return;
    }
    $key = tli_sheet_key();
    $last = get_option('tli_sheet_last', []);
    echo '<div class="tli-card" id="tli-sheet"><h2>Google Sheet sync</h2><p class="muted">One live Google Sheet for products, prices, stock per warehouse and business details. Edits in the sheet reach the site within about 5 minutes (or at once with Schermo → Sync now); changes on the site show up in the sheet the same way.</p>';
    if ($key) {
        printf('<ul class="tli-help"><li>Site address: <code>%s</code></li><li>Sheet key: <code>%s</code> <span class="muted small">— keep it private; anyone with it can change products and stock.</span></li><li>Last sync from the sheet: %s</li></ul>',
            esc_html(home_url()), esc_html($key), !empty($last['at']) ? esc_html(human_time_diff((int) $last['at']) . ' ago · ' . (int) $last['applied'] . ' changes' . ((int) $last['errors'] ? ', ' . (int) $last['errors'] . ' rows with problems' : '')) : 'never');
    }
    printf('<form method="post" action="%s"><input type="hidden" name="action" value="tli_sheet_key">%s<button class="tli-btn%s">%s</button></form>', esc_url(admin_url('admin-post.php')), wp_nonce_field('tli_sheet_key', '_wpnonce', true, false), $key ? '' : ' primary', $key ? 'Create a new key (old one stops working)' : 'Create sheet key');
    echo '<p class="muted small">Set-up: open the Google Sheet → Extensions → Apps Script → paste the script from <code>commerce/storefront/google-sheet/Code.gs</code> → Save → reload the sheet → Schermo → Set up (site address + key).</p></div>';
}
