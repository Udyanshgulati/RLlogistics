<?php
// Loads the client's filled-in listing sheet (Schermo_Product_Listing_Sheet.xlsx) into WooCommerce.
//
//   wp eval-file import-sheet.php <sheet.xlsx> [dry-run]
//
// Matches products by SKU and sizes by variant SKU (MI-001-M). Empty cells change nothing, so a half-filled sheet
// is safe to load and can be loaded again later. Everything runs in one database transaction: dry-run prints
// what would change and rolls it all back. On deploy, docker/setup.sh runs this whenever the sheet file changes.
// Nothing is invented: what the sheet does not say is left as it is and listed under "to do by hand".

defined('ABSPATH') || exit;

$file = null;
$dry = false;
foreach ($args ?? [] as $a) {
    if ($a === 'dry-run' || $a === '--dry-run') {
        $dry = true;
    } else {
        $file = $a;
    }
}
if (!$file || !is_readable($file)) {
    WP_CLI::error('Usage: wp eval-file import-sheet.php <sheet.xlsx> [dry-run]');
}

/** Reads every tab of an .xlsx into [tab => [row => [col letter => value]]]. Formula cells give their cached value. */
$read_xlsx = static function (string $path): array {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException("cannot open {$path}");
    }
    $xml = static function (string $name) use ($zip): ?SimpleXMLElement {
        $s = $zip->getFromName($name);
        return $s === false ? null : simplexml_load_string($s);
    };
    $text = static function (SimpleXMLElement $n): string {
        $n->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        return implode('', array_map('strval', $n->xpath('.//m:t') ?: []));
    };
    $shared = [];
    if ($sst = $xml('xl/sharedStrings.xml')) {
        foreach ($sst->si as $si) {
            $shared[] = $text($si);
        }
    }
    $targets = [];
    foreach ($xml('xl/_rels/workbook.xml.rels')->Relationship as $r) {
        $t = (string) $r['Target'];
        $targets[(string) $r['Id']] = str_starts_with($t, '/') ? ltrim($t, '/') : 'xl/' . $t;
    }
    $book = [];
    foreach ($xml('xl/workbook.xml')->sheets->sheet as $s) {
        $rid = (string) $s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        $sheet = $xml($targets[$rid] ?? '');
        $rows = [];
        foreach ($sheet ? $sheet->sheetData->row : [] as $row) {
            foreach ($row->c as $c) {
                preg_match('/^([A-Z]+)(\d+)$/', (string) $c['r'], $m);
                $t = (string) $c['t'];
                $v = match ($t) {
                    's' => $shared[(int) $c->v] ?? '',
                    'inlineStr' => $text($c->is),
                    'b' => (string) $c->v === '1',
                    default => isset($c->v) ? (string) $c->v : null,
                };
                if ($v !== null && $t === '' && is_numeric($v)) {
                    $v = $v + 0;
                }
                if (is_string($v)) {
                    $v = trim($v);
                }
                if ($v !== null && $v !== '') {
                    $rows[(int) $m[2]][$m[1]] = $v;
                }
            }
        }
        $book[(string) $s['name']] = $rows;
    }
    return $book;
};

/** Turns a tab into a list of [header => value] rows, using row 1 as headers. Skips the EXAMPLE row. */
$table = static function (array $rows, string $key_header): array {
    $head = $rows[1] ?? [];
    $out = [];
    foreach ($rows as $n => $cells) {
        if ($n === 1) {
            continue;
        }
        $r = ['_row' => $n];
        foreach ($head as $col => $h) {
            if (array_key_exists($col, $cells)) {
                $r[$h] = $cells[$col];
            }
        }
        $key = (string) ($r[$key_header] ?? '');
        if ($key === '' || str_starts_with(strtoupper($key), 'EXAMPLE')) {
            continue;
        }
        $out[] = $r;
    }
    return $out;
};

/** Value of the first column whose header starts with $prefix (headers carry hints like "(₹, incl. GST)"). */
$col = static function (array $row, string $prefix) {
    foreach ($row as $h => $v) {
        if (is_string($h) && stripos($h, $prefix) === 0 && !(is_string($v) && str_starts_with($v, '='))) {
            return $v;
        }
    }
    return null;
};
$money = static function ($v): ?string {
    if ($v === null || $v === '') {
        return null;
    }
    $n = preg_replace('/[^\d.]/', '', (string) $v);
    return is_numeric($n) ? wc_format_decimal($n, 2) : null;
};
$count = static function ($v): ?int {
    if ($v === null || $v === '') {
        return null;
    }
    $n = preg_replace('/[^\d]/', '', (string) $v);
    return $n === '' ? null : (int) $n;
};

$changes = [];
$warn = [];
$manual = [];
$note = static function (string $sku, string $what) use (&$changes): void {
    $changes[$sku][] = $what;
};

try {
    $book = $read_xlsx($file);
} catch (Throwable $e) {
    WP_CLI::error('Could not read the sheet: ' . $e->getMessage());
}
foreach (['Products', 'Stock by size'] as $need) {
    if (!isset($book[$need])) {
        WP_CLI::error("The sheet has no '{$need}' tab — is this the Schermo listing sheet?");
    }
}

global $wpdb;
$wpdb->query('START TRANSACTION');

// ---- GST: one tax class per rate, prices entered including GST -------------------------------------------------
$gst_class = static function (float $rate) use (&$changes): string {
    $label = 'GST ' . rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.') . '%';
    $slug = sanitize_title($label);
    if (!in_array($slug, WC_Tax::get_tax_class_slugs(), true)) {
        WC_Tax::create_tax_class($label, $slug);
    }
    $has = array_filter(WC_Tax::get_rates_for_tax_class($slug), static fn ($r) => $r->tax_rate_country === 'IN');
    if (!$has) {
        WC_Tax::_insert_tax_rate(['tax_rate_country' => 'IN', 'tax_rate' => (string) $rate, 'tax_rate_name' => 'GST', 'tax_rate_priority' => 1, 'tax_rate_compound' => 0, 'tax_rate_shipping' => 0, 'tax_rate_order' => 0, 'tax_rate_class' => $slug]);
        $changes['store'][] = "tax rate {$label} for India";
    }
    return $slug;
};
$taxes_used = false;

// ---- Products tab ----------------------------------------------------------------------------------------------
$collection = get_term_by('name', 'Milan Icons', 'product_cat');
$priced = [];
foreach ($table($book['Products'], 'SKU') as $r) {
    $sku = (string) $r['SKU'];
    $id = wc_get_product_id_by_sku($sku);
    $status = strtolower((string) $col($r, 'Show on site'));
    if (!$id && $status === 'remove') {
        continue; // already in the trash
    }
    if (!$id) {
        $warn[] = "{$sku} (row {$r['_row']}) is not on the site — new products need photos and an entry in catalog/*/products.json";
        continue;
    }
    $p = wc_get_product($id);
    if ($status === 'remove') {
        if (get_post_status($id) !== 'trash') {
            wp_trash_post($id);
            $note($sku, 'moved to trash (Show on site = Remove)');
        }
        continue;
    }
    if ($status !== '') {
        $want = $status === 'keep hidden' ? 'draft' : ($status === 'publish' ? 'publish' : null);
        if ($want === null) {
            $warn[] = "{$sku}: unknown 'Show on site' value '{$status}'";
        } elseif ($p->get_status() !== $want) {
            $p->set_status($want);
            $note($sku, "status {$want}");
        }
    }

    if (($v = $col($r, 'Product name')) && $v !== $p->get_name()) {
        $p->set_name((string) $v);
        $note($sku, 'name');
    }
    if ($v = $col($r, 'Category')) {
        // The sheet's Category is the product type. Keep the product's collection (and any other non-type
        // category) as it is, and swap only the type.
        $cat = term_exists((string) $v, 'product_cat') ?: wp_insert_term((string) $v, 'product_cat');
        $type_ids = array_filter(array_map(static fn ($n) => ($t = get_term_by('name', $n, 'product_cat')) ? (int) $t->term_id : 0, ['Clothing', 'Sportswear', 'Accessories', 'Fan Merch']));
        $keep = array_values(array_diff(array_map('intval', $p->get_category_ids()), $type_ids));
        $want = array_values(array_unique(array_merge($keep, [(int) $cat['term_id']])));
        if (array_diff($want, $p->get_category_ids()) || array_diff($p->get_category_ids(), $want)) {
            $p->set_category_ids($want);
            $note($sku, "category {$v}");
        }
    }
    if (($v = $col($r, 'Short description')) && $v !== $p->get_short_description()) {
        $p->set_short_description((string) $v);
        $note($sku, 'short description');
    }
    if (($v = $col($r, 'Full description')) && $v !== '(full text)' && $v !== $p->get_description()) {
        $p->set_description((string) $v);
        $note($sku, 'description');
    }

    // Specifications accordion: shown as visible, non-variation attributes next to Size.
    $attrs = $p->get_attributes();
    $specs = [
        'Material' => $col($r, 'Fabric / material'),
        'Fabric weight' => ($g = $col($r, 'Fabric weight')) !== null ? (is_numeric($g) ? $g . ' GSM' : $g) : null,
        'Fit' => ($f = $col($r, 'Fit')) === 'Not applicable' ? null : $f,
        'Care' => $col($r, 'Care'),
        'Colour' => $col($r, 'Colour'),
        'Made in' => $col($r, 'Made in'),
    ];
    $pos = count($attrs);
    foreach ($specs as $label => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $key = sanitize_title($label);
        $old = isset($attrs[$key]) ? implode(', ', $attrs[$key]->get_options()) : null;
        if ($old === (string) $value) {
            continue;
        }
        $a = new WC_Product_Attribute();
        $a->set_name($label);
        $a->set_options([(string) $value]);
        $a->set_visible(true);
        $a->set_variation(false);
        $a->set_position(isset($attrs[$key]) ? $attrs[$key]->get_position() : $pos++);
        $attrs[$key] = $a;
        $note($sku, strtolower($label));
    }
    $p->set_attributes($attrs);

    // GST and HSN (for invoices). Prices in the sheet already include GST.
    if (($v = $col($r, 'HSN')) !== null && (string) $v !== $p->get_meta('_schermo_hsn')) {
        $p->update_meta_data('_schermo_hsn', (string) $v);
        $note($sku, "HSN {$v}");
    }
    if (($v = $col($r, 'GST rate')) !== null) {
        $rate = (float) preg_replace('/[^\d.]/', '', (string) $v);
        if (is_numeric($v) && $rate > 0 && $rate < 1) {
            $rate *= 100; // Excel stores 5% as 0.05
        }
        $class = $gst_class($rate);
        $taxes_used = true;
        if ($p->get_tax_class() !== $class) {
            $p->set_tax_class($class);
            $p->set_tax_status('taxable');
            $note($sku, "GST {$rate}%");
        }
    }

    // Packed weight and size, for courier rates.
    if (($g = $count($col($r, 'Packed weight'))) !== null) {
        $w = wc_format_decimal(get_option('woocommerce_weight_unit') === 'g' ? $g : $g / 1000, 3);
        if ((string) $p->get_weight() !== (string) $w) {
            $p->set_weight($w);
            $note($sku, "weight {$g} g");
        }
    }
    if (($v = $col($r, 'Packed size')) !== null) {
        $d = preg_split('/\s*[×xX*]\s*/u', (string) $v);
        if (count($d) === 3 && array_filter($d, 'is_numeric') === $d) {
            [$l, $wd, $h] = $d;
            if ([$p->get_length(), $p->get_width(), $p->get_height()] !== [$l, $wd, $h]) {
                $p->set_length($l);
                $p->set_width($wd);
                $p->set_height($h);
                $note($sku, "size {$l}×{$wd}×{$h} cm");
            }
        } else {
            $warn[] = "{$sku}: packed size '{$v}' is not L×W×H";
        }
    }

    // Price. Sale price is optional; MRP is kept for invoices and labels.
    $price = $money($col($r, 'Selling price'));
    $sale = $money($col($r, 'Sale price'));
    if (($mrp = $money($col($r, 'MRP'))) !== null && $mrp !== $p->get_meta('_schermo_mrp')) {
        $p->update_meta_data('_schermo_mrp', $mrp);
        $note($sku, "MRP ₹{$mrp}");
    }
    if ($sale !== null && $price !== null && (float) $sale >= (float) $price) {
        $warn[] = "{$sku}: sale price ₹{$sale} is not below the selling price ₹{$price} — sale price ignored";
        $sale = null;
    }
    if ($price !== null) {
        $priced[$sku] = ['id' => $id, 'price' => $price, 'sale' => $sale];
        if ($p->is_type('simple')) {
            if ($p->get_regular_price() !== $price || $p->get_sale_price() !== (string) $sale) {
                $p->set_regular_price($price);
                $p->set_sale_price((string) $sale);
                $note($sku, "price ₹{$price}" . ($sale ? ", sale ₹{$sale}" : ''));
            }
        }
    }

    if (($v = $col($r, 'Search keywords')) !== null) {
        $tags = array_values(array_filter(array_map('trim', explode(',', (string) $v))));
        $now = wp_get_post_terms($id, 'product_tag', ['fields' => 'names']);
        sort($tags);
        sort($now);
        if ($tags !== $now) {
            $p->set_tag_ids(array_map(static fn ($t) => (int) (term_exists($t, 'product_tag') ?: wp_insert_term($t, 'product_tag'))['term_id'], $tags));
            $note($sku, 'keywords');
        }
    }
    if (($v = $col($r, 'Photos OK')) && stripos((string) $v, 'yes') !== 0) {
        $manual[] = "{$sku}: photos — client says '{$v}'";
    }
    if ($v = $col($r, 'Notes for us')) {
        $manual[] = "{$sku}: client note — {$v}";
    }
    $p->save();
}

// ---- Stock by size tab -----------------------------------------------------------------------------------------
$stock_rows = [];
foreach ($table($book['Stock by size'], 'Variant SKU') as $r) {
    $stock_rows[(string) ($r['Product SKU'] ?? '')][] = $r;
}
foreach ($stock_rows as $psku => $rows) {
    $pid = wc_get_product_id_by_sku($psku);
    if (!$pid || get_post_status($pid) === 'trash') {
        continue;
    }
    $filled = array_filter($rows, static fn ($r) => $count($col($r, 'Stock quantity')) !== null);
    $base = $priced[$psku] ?? null;
    foreach ($rows as $r) {
        $vsku = (string) $r['Variant SKU'];
        $vid = wc_get_product_id_by_sku($vsku);
        if (!$vid) {
            $warn[] = "{$vsku} (row {$r['_row']}) not found on the site";
            continue;
        }
        $v = wc_get_product($vid);
        $qty = $count($col($r, 'Stock quantity'));
        // Never sell what has not been counted: once a product has a price, sizes left blank are stocked at 0.
        if ($qty === null && ($filled || $base)) {
            $qty = 0;
            if (!$filled) {
                $warn[] = "{$psku}: has a price but no stock filled in — all sizes set to 0 (shows Sold out)";
            }
        }
        if ($qty !== null && (!$v->get_manage_stock() || (int) $v->get_stock_quantity() !== $qty)) {
            $v->set_manage_stock(true);
            $v->set_stock_quantity($qty);
            $note($vsku, "stock {$qty}");
        }
        if (($low = $count($col($r, 'Reorder at'))) !== null && (int) $v->get_low_stock_amount() !== $low) {
            $v->set_low_stock_amount($low);
            $note($vsku, "low-stock alert at {$low}");
        }
        if (($ean = $col($r, 'Barcode')) !== null && method_exists($v, 'set_global_unique_id') && (string) $ean !== $v->get_global_unique_id()) {
            try {
                $v->set_global_unique_id((string) $ean);
                $note($vsku, "barcode {$ean}");
            } catch (Throwable $e) {
                $warn[] = "{$vsku}: barcode {$ean} rejected — " . $e->getMessage();
            }
        }
        if ($v->is_type('variation')) {
            $own = $money($col($r, 'Price different'));
            $price = $own ?? ($base['price'] ?? null);
            $sale = $own !== null ? '' : (string) ($base['sale'] ?? '');
            if ($price !== null && ($v->get_regular_price() !== $price || $v->get_sale_price() !== $sale)) {
                $v->set_regular_price($price);
                $v->set_sale_price($sale);
                $note($vsku, "price ₹{$price}" . ($sale !== '' ? ", sale ₹{$sale}" : ''));
            }
        }
        $v->save();
    }
    if (wc_get_product($pid)->is_type('variable')) {
        WC_Product_Variable::sync($pid);
    }
}
// Variable products with a price but no rows on the Stock tab still get their sizes priced (and stocked at 0).
foreach ($priced as $sku => $b) {
    if (isset($stock_rows[$sku]) || !($p = wc_get_product($b['id'])) || !$p->is_type('variable')) {
        continue;
    }
    foreach ($p->get_children() as $vid) {
        $v = wc_get_product($vid);
        $v->set_regular_price($b['price']);
        $v->set_sale_price((string) $b['sale']);
        if (!$v->get_manage_stock()) {
            $v->set_manage_stock(true);
            $v->set_stock_quantity(0);
        }
        $v->save();
    }
    WC_Product_Variable::sync($b['id']);
    $warn[] = "{$sku}: priced but not on the Stock tab — sizes stocked at 0";
}

if ($taxes_used && get_option('woocommerce_calc_taxes') !== 'yes') {
    update_option('woocommerce_calc_taxes', 'yes');
    update_option('woocommerce_prices_include_tax', 'yes');
    update_option('woocommerce_tax_based_on', 'shipping');
    update_option('woocommerce_tax_display_shop', 'incl');
    update_option('woocommerce_tax_display_cart', 'incl');
    $changes['store'][] = 'GST switched on (prices include GST)';
}

// ---- Size chart tab --------------------------------------------------------------------------------------------
if (isset($book['Size chart'])) {
    $html = '';
    $rows = $book['Size chart'];
    $nums = array_keys($rows);
    foreach ($nums as $i => $n) {
        $a = (string) ($rows[$n]['A'] ?? '');
        $next = $rows[$nums[$i + 1] ?? -1]['A'] ?? '';
        if ($next === 'Size' && count($rows[$n]) === 1) {
            // A block: title row, header row, then one row per size until the next gap.
            $head = $rows[$nums[$i + 1]];
            $body = [];
            for ($k = $nums[$i + 1] + 1; isset($rows[$k]); $k++) {
                if (count($rows[$k]) > 1) {
                    $body[] = $rows[$k];
                }
            }
            if (!$body) {
                continue;
            }
            $cols = array_values(array_filter(array_keys($head), static fn ($c) => $c === 'A' || array_filter($body, static fn ($b) => isset($b[$c]))));
            $title = trim(preg_replace('/\s*\(.*\)\s*$/', '', $a));
            $html .= '<h3>' . esc_html($title) . '</h3><table><thead><tr>';
            foreach ($cols as $c) {
                $html .= '<th>' . esc_html((string) $head[$c]) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($body as $b) {
                $html .= '<tr>';
                foreach ($cols as $c) {
                    $html .= '<td>' . esc_html((string) ($b[$c] ?? '')) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        } elseif (str_starts_with($a, 'Caps, beanie, socks')) {
            $fit = trim(implode(' ', array_diff_key($rows[$n], ['A' => 1])));
            if ($fit !== '') {
                $html .= '<h3>Caps, beanie and socks</h3><p>' . esc_html($fit) . '</p>';
            }
        }
    }
    if ($html !== '' && $html !== get_theme_mod('brandhouse_size_guide')) {
        set_theme_mod('brandhouse_size_guide', $html);
        $changes['store'][] = 'size guide';
    }
}

// ---- Store details & policies tab ------------------------------------------------------------------------------
if (isset($book['Store details & policies'])) {
    $ans = [];
    foreach ($book['Store details & policies'] as $n => $r) {
        if ($n > 1 && isset($r['A'], $r['B'])) {
            $ans[(string) $r['A']] = $r['B'];
        }
    }
    $q = static function (string $prefix) use ($ans) {
        foreach ($ans as $k => $v) {
            if (stripos($k, $prefix) === 0) {
                return $v;
            }
        }
        return null;
    };
    $store = static function (string $what) use (&$changes): void {
        $changes['store'][] = $what;
    };

    // Business details: kept for invoices; footer/contact pages are edited by hand.
    $biz = array_filter([
        'legal_name' => $q('Legal business name'), 'gstin' => $q('GSTIN'), 'address' => $q('Registered address'),
        'email' => $q('Customer support email'), 'phone' => $q('Customer support phone'), 'social' => $q('Instagram'),
    ]);
    if ($biz && $biz !== get_option('schermo_business')) {
        update_option('schermo_business', $biz, false);
        $store('business details (' . implode(', ', array_keys($biz)) . ')');
        $manual[] = 'Business details: put the address and support contact in the footer / contact page; set WooCommerce → Settings → General store address';
    }

    // Shipping in India: flat rate below the free-shipping minimum.
    $zone = null;
    foreach (WC_Shipping_Zones::get_zones() as $z) {
        if ($z['zone_name'] === 'India') {
            $zone = new WC_Shipping_Zone($z['id']);
        }
    }
    $method = static function (WC_Shipping_Zone $zone, string $type) {
        foreach ($zone->get_shipping_methods() as $m) {
            if ($m->id === $type) {
                return $m;
            }
        }
        return WC_Shipping_Zones::get_shipping_method($zone->add_shipping_method($type));
    };
    $set = static function ($m, array $values) use ($store): void {
        $opt = $m->get_instance_option_key();
        $cur = get_option($opt, []);
        $new = array_merge($cur, $values);
        if ($new !== $cur) {
            update_option($opt, $new);
            $store($m->id . ' ' . wp_json_encode($values));
        }
    };
    $charge = $money($q('Shipping charge in India'));
    $free = $money($q('Free shipping in India above'));
    if ($zone && $charge !== null) {
        $set($method($zone, 'flat_rate'), ['title' => 'Standard delivery', 'cost' => $charge, 'tax_status' => 'none', 'enabled' => 'yes']);
    }
    if ($zone && $free !== null) {
        $set($method($zone, 'free_shipping'), ['title' => 'Free delivery', 'requires' => 'min_amount', 'min_amount' => $free, 'ignore_discounts' => 'no', 'enabled' => 'yes']);
        if ($charge !== null) {
            // Hide the paid option once free delivery applies.
            update_option('woocommerce_shipping_hide_rates_when_free', 'yes');
        }
    }
    if (!$zone && ($charge !== null || $free !== null)) {
        $warn[] = 'No "India" shipping zone found — it is created by store-setup.php on container start';
    }

    $days = $q('Delivery time in India');
    $from = (string) ($q('Ships from') ?? '');
    if ($days) {
        $text = ($from !== '' ? "Ships from {$from}. " : '') . "Delivery in India: {$days}.";
        if ($text !== get_theme_mod('brandhouse_delivery_text')) {
            set_theme_mod('brandhouse_delivery_text', $text);
            $store('delivery text');
        }
    }

    if (($cod = $q('Cash on Delivery')) !== null) {
        $on = stripos((string) $cod, 'yes') === 0;
        $cur = get_option('woocommerce_cod_settings', []);
        $new = array_merge(['title' => 'Cash on delivery', 'description' => 'Pay in cash when your order is delivered.'], $cur, ['enabled' => $on ? 'yes' : 'no']);
        if ($new !== $cur) {
            update_option('woocommerce_cod_settings', $new);
            $store('cash on delivery ' . ($on ? 'on' : 'off'));
        }
        if ($on && preg_match('/\d/', (string) $cod)) {
            $manual[] = "COD extra charge ('{$cod}') — WooCommerce has no COD fee built in; needs a small plugin or code";
        }
    }

    // Where we sell. EU expands to its member states.
    if (($intl = $q('Ship internationally')) !== null) {
        $eu = ['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'];
        $alias = ['USA' => 'US', 'UNITED STATES' => 'US', 'UK' => 'GB', 'UNITED KINGDOM' => 'GB', 'UAE' => 'AE', 'DUBAI' => 'AE', 'UNITED ARAB EMIRATES' => 'AE'];
        $all = WC()->countries->get_countries();
        $codes = ['IN'];
        $no = preg_match('/^\s*(no|not yet|none)\b/i', (string) $intl);
        foreach ($no ? [] : preg_split('/\s*(?:,|;|\/|\band\b)\s*/i', (string) $intl) as $t) {
            $u = strtoupper(trim($t, " .–-"));
            if ($u === '' || $u === 'INDIA') {
                continue;
            }
            if ($u === 'EU' || $u === 'EUROPE') {
                array_push($codes, ...$eu);
            } elseif (isset($alias[$u])) {
                $codes[] = $alias[$u];
            } elseif (isset($all[$u])) {
                $codes[] = $u;
            } elseif ($c = array_search(strtolower($t), array_map('strtolower', $all), true)) {
                $codes[] = $c;
            } else {
                $warn[] = "Shipping countries: could not match '{$t}'";
            }
        }
        $codes = array_values(array_unique($codes));
        if (get_option('woocommerce_allowed_countries') !== 'specific' || get_option('woocommerce_specific_allowed_countries') !== $codes) {
            update_option('woocommerce_allowed_countries', 'specific');
            update_option('woocommerce_specific_allowed_countries', $codes);
            update_option('woocommerce_ship_to_countries', '');
            $store('selling to ' . implode(', ', $codes));
        }
        if (count($codes) > 1) {
            $manual[] = 'International shipping rate — the sheet does not ask for one; add it in WooCommerce → Settings → Shipping → "Rest of the world"';
        }
    }
    if ($v = $q('Courier partner')) {
        $manual[] = "Courier: {$v} — install and connect its WooCommerce plugin";
    }

    $ret = array_filter([
        'Returns' => $q('Returns allowed'), 'Size exchanges' => $q('Exchanges'), 'Not returnable' => $q('Items that cannot be returned'),
        'Return shipping' => $q('Who pays return shipping'), 'Refunds' => $q('Refund method'),
    ]);
    if ($ret) {
        $text = implode("\n", array_map(static fn ($k, $v) => "{$k}: {$v}", array_keys($ret), $ret));
        if ($text !== get_theme_mod('brandhouse_returns_text')) {
            set_theme_mod('brandhouse_returns_text', $text);
            $store('returns text');
        }
        $manual[] = 'Returns: also publish a full Refund and Returns page (Pages → Refund and Returns Policy)';
    }

    if ($v = $q('Launch date')) {
        $when = is_numeric($v)
            ? (new DateTimeImmutable('1899-12-30', wp_timezone()))->modify('+' . (int) round($v * 86400) . ' seconds') // Excel date
            : date_create_immutable(str_replace(',', '', (string) $v), wp_timezone());
        if ($when && $when->getTimestamp() > time()) {
            $at = $when->format('Y-m-d\TH:i');
            if ($at !== get_theme_mod('brandhouse_drop_at')) {
                set_theme_mod('brandhouse_drop_at', $at);
                $store("drop countdown {$at}");
            }
        } else {
            $warn[] = "Launch date '{$v}' is not a future date — countdown not set";
        }
    }
    if (($v = $q('Announcement bar')) && $v !== get_theme_mod('brandhouse_announcement')) {
        set_theme_mod('brandhouse_announcement', (string) $v);
        $store('announcement bar');
    }
    if (($v = $q('Brand story')) && $v !== get_theme_mod('schermo_story_text')) {
        set_theme_mod('schermo_story_text', (string) $v);
        $store('brand story');
    }
}

// ---- Report ----------------------------------------------------------------------------------------------------
$wpdb->query($dry ? 'ROLLBACK' : 'COMMIT');
wp_cache_flush();
WP_CLI::log(($dry ? '[dry run — nothing saved] ' : '') . 'Sheet: ' . basename($file));
foreach ($changes as $k => $list) {
    WP_CLI::log("  {$k}: " . implode('; ', $list));
}
WP_CLI::log('  ' . count($changes) . ' items changed');
foreach (array_unique($warn) as $w) {
    WP_CLI::warning($w);
}
foreach (array_unique($manual) as $m) {
    WP_CLI::log("  to do by hand: {$m}");
}
if (!$dry) {
    update_option('schermo_sheet_sha', hash_file('sha256', $file), false);
}
