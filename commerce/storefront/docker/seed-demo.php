<?php
// Storefront structure (size attribute, categories, menu, homepage). With argument "1", also a demo catalogue whose
// every item is labelled "Demo": no real product names, photos or prices.
$withDemo = ($args[0] ?? '1') === '1';
$sizes = ['S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
if (!wc_attribute_taxonomy_id_by_name('size')) {
    wc_create_attribute(['name' => 'Size', 'slug' => 'size', 'type' => 'select', 'order_by' => 'menu_order']);
}
if (!taxonomy_exists('pa_size')) {
    register_taxonomy('pa_size', 'product');
}
foreach ($sizes as $i => $s) {
    if (!term_exists(strtolower($s), 'pa_size')) {
        $t = wp_insert_term($s, 'pa_size', ['slug' => strtolower($s)]);
        update_term_meta($t['term_id'], 'order', $i);
    }
}
$cat = [];
foreach (['Clothing', 'Sportswear', 'Fan Merch', 'Accessories'] as $c) {
    $t = term_exists($c, 'product_cat') ?: wp_insert_term($c, 'product_cat');
    $cat[$c] = (int) $t['term_id'];
}

// Primary menu with the four categories.
if (!wp_get_nav_menu_object('Primary')) {
    $menu = wp_create_nav_menu('Primary');
    foreach ($cat as $id) {
        wp_update_nav_menu_item($menu, 0, ['menu-item-object' => 'product_cat', 'menu-item-object-id' => $id, 'menu-item-type' => 'taxonomy', 'menu-item-status' => 'publish']);
    }
    set_theme_mod('nav_menu_locations', array_merge((array) get_theme_mod('nav_menu_locations', []), ['primary' => $menu]));
}

// Static front page (rendered by the theme's front-page.php).
if ((int) get_option('page_on_front') === 0) {
    $home = wp_insert_post(['post_type' => 'page', 'post_title' => 'Home', 'post_name' => 'home', 'post_status' => 'publish']);
    update_option('show_on_front', 'page');
    update_option('page_on_front', $home);
}

if (!$withDemo) {
    echo "structure ready, no demo products\n";
    return;
}
$desc = 'Demo product for layout preview only. Real product name, photos, description and price will be supplied by Schermo.';
$items = [
    ['Demo Oversized Tee', 'Clothing', 1499, ['xxxl' => 0]],
    ['Demo Heavyweight Hoodie', 'Clothing', 3499, ['s' => 0]],
    ['Demo Relaxed Cargo Pant', 'Clothing', 2999, []],
    ['Demo Training Jersey', 'Sportswear', 1999, ['xl' => 0, 'xxl' => 0]],
    ['Demo Track Jacket', 'Sportswear', 3999, []],
    ['Demo Running Short', 'Sportswear', 1299, []],
    ['Demo Supporter Jersey', 'Fan Merch', 2499, ['m' => 0]],
    ['Demo Fan Tee', 'Fan Merch', 1299, [], 999],
    ['Demo Fan Scarf', 'Accessories', 899, null],
    ['Demo Cap', 'Accessories', 999, null],
    ['Demo Tote Bag', 'Accessories', 799, null],
];
$n = 0;
foreach ($items as $it) {
    [$name, $c, $price, $out] = $it;
    $sale = $it[4] ?? null;
    $n++;
    $sku = 'SCH-DEMO-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    if (wc_get_product_id_by_sku($sku)) {
        continue;
    }
    if ($out === null) {
        $p = new WC_Product_Simple();
        $p->set_name($name); $p->set_regular_price((string) $price); $p->set_sku($sku);
        $p->set_manage_stock(true); $p->set_stock_quantity($n % 2 ? 12 : 0);
        $p->set_description($desc); $p->set_category_ids([$cat[$c]]); $p->save();
        continue;
    }
    $p = new WC_Product_Variable();
    $p->set_name($name); $p->set_sku($sku); $p->set_description($desc); $p->set_category_ids([$cat[$c]]);
    $a = new WC_Product_Attribute();
    $a->set_id(wc_attribute_taxonomy_id_by_name('size')); $a->set_name('pa_size');
    $a->set_options(array_map(static fn ($s) => get_term_by('slug', strtolower($s), 'pa_size')->term_id, $sizes));
    $a->set_visible(true); $a->set_variation(true);
    $p->set_attributes([$a]);
    $id = $p->save();
    foreach ($sizes as $s) {
        $slug = strtolower($s);
        $v = new WC_Product_Variation();
        $v->set_parent_id($id); $v->set_attributes(['pa_size' => $slug]);
        $v->set_regular_price((string) $price);
        if ($sale) {
            $v->set_sale_price((string) $sale);
        }
        $v->set_sku($sku . '-' . $s); $v->set_manage_stock(true); $v->set_stock_quantity($out[$slug] ?? 8);
        $v->save();
    }
    WC_Product_Variable::sync($id);
}
echo "structure and demo catalogue ready\n";
