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

$added = 0;
foreach (glob($root . '/*/products.json') ?: [] as $json) {
    $data = json_decode((string) file_get_contents($json), true);
    $collection = $cat($data['collection']);
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
