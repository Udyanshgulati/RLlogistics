<?php
// Imports catalog/*/products.json (copied into the image at /opt/schermo/catalog). Idempotent: products are matched by
// SKU and never overwritten, so edits made in WordPress admin are kept. Prices and stock are left for the owner to set.
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$root = getenv('CATALOG_DIR') ?: '/opt/schermo/catalog';
$sizes = ['S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
$cat = static function (string $name, int $parent = 0): int {
    $t = term_exists($name, 'product_cat', $parent) ?: wp_insert_term($name, 'product_cat', ['parent' => $parent]);
    return (int) $t['term_id'];
};
$attach = static function (string $file, int $post, string $title): int {
    $tmp = wp_tempnam(basename($file));
    copy($file, $tmp);
    $id = media_handle_sideload(['name' => basename($file), 'tmp_name' => $tmp], $post, $title);
    return is_wp_error($id) ? 0 : (int) $id;
};

// Prices from the client's sheet: regular = MRP, sale = selling price. Applied once per price_version, so prices
// changed later in WordPress admin are not overwritten on the next deploy.
$apply_price = static function (int $id, array $p): void {
    $ver = (int) ($p['price_version'] ?? 0);
    if ($ver < 1 || (int) get_post_meta($id, '_bh_price_ver', true) >= $ver) {
        return;
    }
    $product = wc_get_product($id);
    $targets = $product->is_type('variable') ? array_map('wc_get_product', $product->get_children()) : [$product];
    foreach (array_filter($targets) as $t) {
        $t->set_regular_price((string) $p['regular_price']);
        $t->set_sale_price((string) ($p['sale_price'] ?? ''));
        $t->save();
    }
    if ($product->is_type('variable')) {
        WC_Product_Variable::sync($id);
    }
    wc_delete_product_transients($id);
    update_post_meta($id, '_bh_price_ver', $ver);
    echo "catalog: price set for {$p['sku']}\n";
};

$added = 0;
foreach (glob($root . '/*/products.json') ?: [] as $json) {
    $data = json_decode((string) file_get_contents($json), true);
    $collection = $cat($data['collection']);
    // The collection's line (shown on its shop page), only while the term has none — admin edits are kept.
    if (!empty($data['collection_description']) && term_description($collection, 'product_cat') === '') {
        wp_update_term($collection, 'product_cat', ['description' => $data['collection_description']]);
    }
    // Put the collection first in the main menu, once.
    $loc = get_nav_menu_locations();
    if (!empty($loc['primary'])) {
        $linked = array_filter(wp_get_nav_menu_items($loc['primary']) ?: [], static fn ($i) => (int) $i->object_id === $collection && $i->object === 'product_cat');
        if (!$linked) {
            wp_update_nav_menu_item($loc['primary'], 0, ['menu-item-object' => 'product_cat', 'menu-item-object-id' => $collection, 'menu-item-type' => 'taxonomy', 'menu-item-status' => 'publish', 'menu-item-position' => 0]);
        }
    }
    // Products the owner removed from the catalogue go to the trash (restorable from Products → Trash).
    foreach ($data['removed'] ?? [] as $sku) {
        $pid = wc_get_product_id_by_sku($sku);
        if ($pid && get_post_status($pid) !== 'trash') {
            wp_trash_post($pid);
            echo "catalog: removed {$sku}\n";
        }
    }
    foreach ($data['products'] as $p) {
        $ver = (int) ($p['image_version'] ?? $data['image_version'] ?? 1);
        $existing = wc_get_product_id_by_sku($p['sku']);
        if ($existing) {
            $apply_price($existing, $p);
            // Existing products keep everything the owner edited; only their photos are replaced when the
            // catalogue's image_version goes up.
            if ((int) get_post_meta($existing, '_bh_img_ver', true) < $ver) {
                $prod = wc_get_product($existing);
                $old = array_filter(array_merge([(int) $prod->get_image_id()], array_map('intval', $prod->get_gallery_image_ids())));
                $new = array_values(array_filter(array_map(static fn ($i, $img) => $attach(dirname($json) . '/' . $img, $existing, $p['name'] . ' — view ' . ($i + 1)), array_keys($p['images']), $p['images'])));
                if ($new) {
                    $prod->set_image_id($new[0]);
                    $prod->set_gallery_image_ids(array_slice($new, 1));
                    $prod->save();
                    foreach ($old as $oid) {
                        wp_delete_attachment($oid, true);
                    }
                    update_post_meta($existing, '_bh_img_ver', $ver);
                    echo "catalog: new photos for {$p['sku']}\n";
                }
            }
            continue;
        }
        // A product the owner trashed in WP admin stays trashed (WooCommerce's SKU lookup ignores the trash).
        global $wpdb;
        if ($wpdb->get_var($wpdb->prepare("SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_sku' WHERE m.meta_value = %s AND p.post_type = 'product' AND p.post_status = 'trash' LIMIT 1", $p['sku']))) {
            continue;
        }
        $product = $p['sizes'] ? new WC_Product_Variable() : new WC_Product_Simple();
        $product->set_name($p['name']);
        $product->set_sku($p['sku']);
        $product->set_description($p['description']);
        $product->set_category_ids([$collection, $cat($p['category'])]);
        $product->set_status('publish');
        if ($p['sizes']) {
            $a = new WC_Product_Attribute();
            $a->set_id(wc_attribute_taxonomy_id_by_name('size'));
            $a->set_name('pa_size');
            $a->set_options(array_map(static fn ($s) => get_term_by('slug', strtolower($s), 'pa_size')->term_id, $sizes));
            $a->set_visible(true);
            $a->set_variation(true);
            $product->set_attributes([$a]);
        }
        $id = $product->save();
        $images = [];
        foreach ($p['images'] as $i => $img) {
            $images[] = $attach(dirname($json) . '/' . $img, $id, $p['name'] . ' — view ' . ($i + 1));
        }
        $images = array_values(array_filter($images));
        update_post_meta($id, '_bh_img_ver', (int) ($p['image_version'] ?? $data['image_version'] ?? 1));
        if ($images) {
            $product->set_image_id($images[0]);
            $product->set_gallery_image_ids(array_slice($images, 1));
            $product->save();
        }
        if ($p['sizes']) {
            foreach ($sizes as $s) {
                $v = new WC_Product_Variation();
                $v->set_parent_id($id);
                $v->set_attributes(['pa_size' => strtolower($s)]);
                $v->set_sku($p['sku'] . '-' . $s);
                $v->save();
            }
            WC_Product_Variable::sync($id);
        }
        $apply_price($id, $p);
        $added++;
    }
}
// Once real products exist, the labelled demo products go to the trash (restorable from Products → Trash).
if ($added > 0) {
    foreach (wc_get_products(['limit' => -1, 'status' => 'publish', 'return' => 'ids']) as $pid) {
        $sku = (string) get_post_meta($pid, '_sku', true);
        if (str_starts_with($sku, 'SCH-DEMO-')) {
            wp_trash_post($pid);
        }
    }
}
echo "catalog: {$added} products added\n";

// Product types (v1): finer types under Clothing and Accessories, assigned from each product's name. A product keeps
// its parent type and collection; only one sub-type is added. Runs once per version and only fills products that
// have no sub-type yet, so changes made in WP admin are kept.
$subtypes = [
    'clothing' => [
        'shorts' => ['Shorts', 'Cargo, layered, denim and practice shorts.', ['shorts']],
        'knitwear-hoodies' => ['Knitwear & Hoodies', 'Winter warmers, sweaters and hoodies.', ['hoodie', 'warmer', 'sweater']],
        'trousers' => ['Trousers', 'Baggy trousers, denim, cargos and track trousers.', ['trouser', 'denim', 'cargo', 'pants']],
        't-shirts-polos' => ['T-shirts & Polos', 'Oversized tees, jerseys and polos.', ['tee', 't-shirt', 'polo', 'jersey']],
    ],
    'accessories' => [
        'caps-hats' => ['Caps & Hats', 'Caps, bucket hats and beanies.', ['cap', 'hat', 'beanie']],
        'bags-backpacks' => ['Bags & Backpacks', 'Backpacks, kit bags and travel bags.', ['backpack', 'rucksack', 'kit bag', 'travel bag', 'duffel', ' bag']],
        'other-accessories' => ['Other Accessories', 'Socks, sippers, sneakers and other everyday pieces.', []],
    ],
];
if ((int) get_option('schermo_types_v') < 1) {
    $n = 0;
    foreach ($subtypes as $parent_slug => $children) {
        $parent = get_term_by('slug', $parent_slug, 'product_cat');
        if (!$parent) {
            continue;
        }
        $ids = [];
        foreach ($children as $slug => [$name, $desc]) {
            $t = get_term_by('slug', $slug, 'product_cat') ?: (object) wp_insert_term($name, 'product_cat', ['slug' => $slug, 'parent' => $parent->term_id, 'description' => $desc]);
            $ids[$slug] = (int) $t->term_id;
        }
        foreach (wc_get_products(['status' => ['publish', 'draft', 'private'], 'limit' => -1, 'category' => [$parent_slug]]) as $prod) {
            $cats = $prod->get_category_ids();
            if (array_intersect($cats, $ids)) {
                continue;
            }
            $name = ' ' . strtolower($prod->get_name());
            $pick = array_key_last($children); // the last child is the catch-all (or the most general match)
            foreach ($children as $slug => [, , $words]) {
                foreach ($words as $w) {
                    if (str_contains($name, $w) && !str_contains($name, 'bag tag')) {
                        $pick = $slug;
                        break 2;
                    }
                }
            }
            $prod->set_category_ids(array_values(array_unique(array_merge($cats, [$ids[$pick]]))));
            $prod->save();
            $n++;
        }
    }
    update_option('schermo_types_v', 1, false);
    echo "catalog: product types assigned to {$n} products\n";
}
// Short, specific lines for the top-level type pages (only while a page has none — admin edits are kept).
foreach ([
    'clothing' => 'Tees, polos, trousers, shorts and knitwear from Schermo Core, Schermo Atelier and the team collections.',
    'accessories' => 'Caps and hats, bags and backpacks, and everyday accessories.',
    'sportswear' => 'Match and practice kits, track trousers and playing gear from Milan Icons and Glasgow Cosmic.',
    'fan-merch' => 'Bag tags, lanyards and keychains for Milan Icons and Glasgow Cosmic supporters.',
] as $slug => $line) {
    $t = get_term_by('slug', $slug, 'product_cat');
    if ($t && $t->description === '') {
        wp_update_term($t->term_id, 'product_cat', ['description' => $line]);
    }
}

// One-time menu (v4): New In · Shop ▾ (types, grouped) · Collections ▾ (the four collections).
// Runs once per version, so menus edited later in WP admin (Appearance → Menus) stay as they are.
$loc = get_nav_menu_locations();
if (!empty($loc['primary']) && (int) get_option('schermo_menu_v') < 4) {
    $menu = (int) $loc['primary'];
    foreach (wp_get_nav_menu_items($menu) ?: [] as $item) {
        wp_delete_post($item->ID, true);
    }
    $pos = 0;
    $add = static function (array $a) use ($menu, &$pos): int {
        return (int) wp_update_nav_menu_item($menu, 0, $a + ['menu-item-status' => 'publish', 'menu-item-position' => ++$pos]);
    };
    $term = static fn (string $slug, int $parent = 0): array => ($t = get_term_by('slug', $slug, 'product_cat')) ? ['menu-item-type' => 'taxonomy', 'menu-item-object' => 'product_cat', 'menu-item-object-id' => $t->term_id, 'menu-item-parent-id' => $parent] : [];
    $add(['menu-item-title' => 'New In', 'menu-item-type' => 'custom', 'menu-item-url' => add_query_arg('orderby', 'date', wc_get_page_permalink('shop'))]);
    $shop = $add(['menu-item-title' => 'Shop', 'menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => wc_get_page_id('shop')]);
    foreach (['clothing' => array_keys($subtypes['clothing']), 'accessories' => array_keys($subtypes['accessories']), 'sportswear' => [], 'fan-merch' => []] as $top => $kids) {
        $a = $term($top, $shop);
        if (!$a) {
            continue;
        }
        $pid = $add($a);
        usort($kids, static fn ($x, $y) => array_search($x, ['t-shirts-polos', 'trousers', 'shorts', 'knitwear-hoodies', 'caps-hats', 'bags-backpacks', 'other-accessories'], true) <=> array_search($y, ['t-shirts-polos', 'trousers', 'shorts', 'knitwear-hoodies', 'caps-hats', 'bags-backpacks', 'other-accessories'], true));
        foreach ($kids as $k) {
            if ($b = $term($k, $pid)) {
                $add($b);
            }
        }
    }
    $coll = $add(['menu-item-title' => 'Collections', 'menu-item-type' => 'custom', 'menu-item-url' => home_url('/#collections')]);
    foreach (['schermo-core', 'schermo-atelier', 'milan-icons', 'glasgow-cosmic'] as $slug) {
        if ($a = $term($slug, $coll)) {
            $add($a);
        }
    }
    update_option('schermo_menu_v', 4, false);
    echo "catalog: menu rebuilt (New In, Shop by type, Collections)\n";
}


// Collections page (v5): a real /collections/ page (theme template page-collections.php), and the menu's
// "Collections" points to it instead of the homepage section. Runs once.
if ((int) get_option('schermo_menu_v') < 5) {
    $page = get_page_by_path('collections', OBJECT, 'page');
    $pid = $page ? (int) $page->ID : (int) wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Collections', 'post_name' => 'collections', 'post_content' => '']);
    if ($page && $page->post_status !== 'publish') {
        wp_update_post(['ID' => $pid, 'post_status' => 'publish']);
    }
    $loc = get_nav_menu_locations();
    if (!empty($loc['primary']) && $pid) {
        foreach (wp_get_nav_menu_items((int) $loc['primary']) ?: [] as $item) {
            if ($item->type === 'custom' && $item->title === 'Collections' && !$item->menu_item_parent) {
                wp_update_nav_menu_item((int) $loc['primary'], $item->ID, [
                    'menu-item-title' => 'Collections', 'menu-item-type' => 'post_type', 'menu-item-object' => 'page',
                    'menu-item-object-id' => $pid, 'menu-item-status' => 'publish', 'menu-item-position' => (int) $item->menu_order, 'menu-item-parent-id' => 0,
                ]);
            }
        }
    }
    update_option('schermo_menu_v', 5, false);
    echo "catalog: Collections page ready\n";
}

// One-time rename (names v2): Italian, fashion-led names for Schermo Core and Schermo Atelier. A product is renamed
// only while it still has its previous catalogue name (and description), so edits made in WP admin are kept.
if ((int) get_option('schermo_names_v') < 2) {
    $n = 0;
    foreach (glob($root . '/*/products.json') ?: [] as $json) {
        $data = json_decode((string) file_get_contents($json), true);
        foreach ($data['products'] ?? [] as $p) {
            if (empty($p['previous_name']) || !($id = wc_get_product_id_by_sku($p['sku']))) {
                continue;
            }
            $prod = wc_get_product($id);
            if ($prod->get_name() !== $p['previous_name']) {
                continue;
            }
            $prod->set_name($p['name']);
            $prod->set_slug(wp_unique_post_slug(sanitize_title($p['name']), $id, $prod->get_status(), 'product', 0));
            if (!empty($p['previous_description']) && $prod->get_description() === $p['previous_description']) {
                $prod->set_description($p['description']);
            }
            $prod->save();
            $n++;
        }
    }
    update_option('schermo_names_v', 2, false);
    echo "catalog: {$n} products renamed\n";
}
