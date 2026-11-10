<?php
/**
 * Schermo product presentation: collection/type context on cards and product pages, the Limited label (Schermo
 * Atelier is the limited-drops collection), and related products from the same collection.
 */

defined('ABSPATH') || exit;

/** [collection slug|null, type slug|null] of a product, from its product_cat terms. */
function schermo_product_groups(int $id): array
{
    $slugs = wp_get_post_terms($id, 'product_cat', ['fields' => 'slugs']);
    $slugs = is_wp_error($slugs) ? [] : $slugs;
    $coll = array_values(array_intersect(array_keys(schermo_collection_labels()), $slugs))[0] ?? null;
    $type = array_values(array_intersect(array_keys(schermo_leaf_types()), $slugs))[0] ?? (array_values(array_intersect(array_keys(schermo_types()), $slugs))[0] ?? null);
    return [$coll, $type];
}

// Card line: "Schermo Atelier · Clothing" — collection first, then type; never a list of every category.
add_filter('brandhouse_card_context', static function (string $default, WC_Product $p): string {
    [$coll, $type] = schermo_product_groups($p->get_id());
    $parts = array_filter([$coll ? schermo_collection_labels()[$coll] : '', $type ? (schermo_types()[$type] ?? '') : '']);
    return $parts ? implode(' · ', $parts) : $default;
}, 10, 2);

// Limited: only for pieces in Schermo Atelier, which the owner's deck defines as limited drops.
add_filter('brandhouse_card_label', static function ($label, WC_Product $p) {
    [$coll] = schermo_product_groups($p->get_id());
    return $coll === 'schermo-atelier' ? __('Limited', 'schermo') : $label;
}, 10, 2);

// Product page: collection and type above the title, as links.
add_action('woocommerce_single_product_summary', static function (): void {
    global $product;
    if (!$product instanceof WC_Product) {
        return;
    }
    [$coll, $type] = schermo_product_groups($product->get_id());
    $links = [];
    foreach ([$coll, $type] as $slug) {
        $t = $slug ? get_term_by('slug', $slug, 'product_cat') : null;
        if ($t) {
            $links[] = '<a href="' . esc_url(get_term_link($t)) . '">' . esc_html($t->name) . '</a>';
        }
    }
    if ($links) {
        echo '<p class="pdp-context eyebrow">' . implode('<span aria-hidden="true"> · </span>', $links) . '</p>'; // phpcs:ignore -- escaped above
    }
    if ($coll === 'schermo-atelier') {
        echo '<p class="pdp-limited">' . esc_html__('Limited drop', 'schermo') . '</p>';
    }
}, 4);

// Related products: same collection first (same type preferred), then WooCommerce's own picks.
add_filter('woocommerce_related_products', static function (array $related, int $id, array $args): array {
    [$coll, $type] = schermo_product_groups($id);
    if (!$coll) {
        return $related;
    }
    $base = ['status' => 'publish', 'limit' => 12, 'return' => 'ids', 'exclude' => [$id], 'orderby' => 'rand'];
    $coll_ids = wc_get_products(['limit' => -1] + $base + ['category' => [$coll]]);
    $type_ids = $type ? wc_get_products(['limit' => -1] + $base + ['category' => [$type]]) : [];
    $ids = array_values(array_unique(array_merge(array_intersect($coll_ids, $type_ids), $coll_ids, $related)));
    return array_slice($ids, 0, max(4, (int) ($args['limit'] ?? 4)));
}, 10, 3);

// Empty cart: WooCommerce's "New in store" block shows the newest products, which are mostly not on sale yet
// ("Coming soon"). Show pieces that can be ordered now instead, with the store's own product cards.
add_filter('render_block', static function (string $html, array $block): string {
    if (($block['blockName'] ?? '') !== 'woocommerce/product-new' || !function_exists('schermo_home_available') || !is_cart()) {
        return $html;
    }
    $ids = schermo_home_available(4);
    if (!$ids) {
        return '';
    }
    ob_start();
    echo '<ul class="products columns-4 cart-suggest">';
    foreach ($ids as $id) {
        $GLOBALS['product'] = wc_get_product($id);
        $GLOBALS['post'] = get_post($id);
        setup_postdata($GLOBALS['post']);
        wc_get_template_part('content', 'product');
    }
    wp_reset_postdata();
    echo '</ul>';
    return (string) ob_get_clean();
}, 10, 2);
// …and its heading says what the list really is.
add_filter('render_block', static function (string $html, array $block): string {
    if (($block['blockName'] ?? '') === 'core/heading' && is_cart() && str_contains($html, 'New in store')) {
        return str_replace('New in store', esc_html__('Available to order now', 'schermo'), $html);
    }
    return $html;
}, 10, 2);
