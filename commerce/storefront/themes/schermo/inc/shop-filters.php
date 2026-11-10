<?php
/**
 * Shop filters and sorting on the real WooCommerce product query. Everything is a URL parameter, so a filtered
 * view can be refreshed or shared:
 *   type=clothing            product type (product_cat), on collection pages and the shop
 *   collection=schermo-core  collection (product_cat), on type pages and the shop
 *   filter_size=m,l          size (WooCommerce's own layered-nav parameter for pa_size)
 *   min_price / max_price    price (WooCommerce's own price filter, on the price lookup table)
 *   available=1              only products that can be ordered now: has a price and is in stock
 *   orderby=date|price|price-desc
 * Options are only shown when the catalogue has data for them (see schermo_shop_context()).
 */

defined('ABSPATH') || exit;

/** Product types and collections: slug => short label. Both are product_cat terms, kept apart here. */
function schermo_types(): array
{
    return schermo_leaf_types() + ['clothing' => __('Clothing', 'schermo'), 'accessories' => __('Accessories', 'schermo')];
}

/** The types shoppers browse by (what pills and cards show), in display order. */
function schermo_leaf_types(): array
{
    return [
        't-shirts-polos' => __('T-shirts & Polos', 'schermo'), 'trousers' => __('Trousers', 'schermo'), 'shorts' => __('Shorts', 'schermo'),
        'knitwear-hoodies' => __('Knitwear & Hoodies', 'schermo'), 'sportswear' => __('Sportswear', 'schermo'), 'caps-hats' => __('Caps & Hats', 'schermo'),
        'bags-backpacks' => __('Bags & Backpacks', 'schermo'), 'other-accessories' => __('Other Accessories', 'schermo'), 'fan-merch' => __('Fan Merch', 'schermo'),
    ];
}

/**
 * Type pills for a listing: the leaf types that have products in it (before any type filter). On a Clothing or
 * Accessories page, that page's own sub-types.
 */
function schermo_type_pills(?WP_Term $current): array
{
    $f = schermo_filters();
    $args = ['status' => 'publish', 'limit' => -1, 'return' => 'ids', 'visibility' => 'catalog'];
    $leaf = $current && isset(schermo_leaf_types()[$current->slug]);
    // On a leaf type page (e.g. Shorts) show every type, so shoppers can move sideways; elsewhere only what is here.
    $base = $current && !$leaf ? wc_get_products($args + ['category' => [$current->slug]]) : wc_get_products($args);
    if ($f['collection'] !== '') {
        $base = array_intersect($base, wc_get_products($args + ['category' => [$f['collection']]]));
    }
    $out = [];
    foreach (schermo_leaf_types() as $slug => $label) {
        $t = get_term_by('slug', $slug, 'product_cat');
        if ($t && array_intersect($base, wc_get_products($args + ['category' => [$slug]]))) {
            $out[$slug] = $label;
        }
    }
    return $out;
}

function schermo_collection_labels(): array
{
    return ['schermo-core' => __('Schermo Core', 'schermo'), 'schermo-atelier' => __('Schermo Atelier', 'schermo'), 'milan-icons' => __('Milan Icons', 'schermo'), 'glasgow-cosmic' => __('Glasgow Cosmic', 'schermo')];
}

/** Current filter values from the URL, validated against known slugs. */
function schermo_filters(): array
{
    // phpcs:disable WordPress.Security.NonceVerification -- read-only filters
    $get = static fn (string $k): string => sanitize_text_field(wp_unslash($_GET[$k] ?? ''));
    $type = $get('type');
    $coll = $get('collection');
    $f = [
        'type' => isset(schermo_types()[$type]) ? $type : '',
        'collection' => isset(schermo_collection_labels()[$coll]) ? $coll : '',
        'sizes' => array_values(array_filter(array_map('sanitize_title', explode(',', $get('filter_size'))))),
        'min' => is_numeric($get('min_price')) ? (float) $get('min_price') : null,
        'max' => is_numeric($get('max_price')) ? (float) $get('max_price') : null,
        'available' => $get('available') === '1',
    ];
    // phpcs:enable
    return $f;
}

/** IDs of products that can be ordered now (priced and in stock), from WooCommerce's lookup table. */
function schermo_available_ids(): array
{
    static $ids = null;
    if ($ids === null) {
        global $wpdb;
        $ids = array_map('intval', $wpdb->get_col("SELECT product_id FROM {$wpdb->wc_product_meta_lookup} WHERE max_price > 0 AND stock_status = 'instock'")); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }
    return $ids;
}

// Apply type, collection and availability to the main shop query (size and price are applied by WooCommerce).
add_action('woocommerce_product_query', static function (WP_Query $q): void {
    $f = schermo_filters();
    $tax = (array) $q->get('tax_query');
    foreach (['type', 'collection'] as $k) {
        if ($f[$k] !== '') {
            $tax[] = ['taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => [$f[$k]]];
        }
    }
    $q->set('tax_query', $tax);
    if ($f['available']) {
        $q->set('post__in', schermo_available_ids() ?: [0]);
    }
});

// Sorting: only orders the catalogue can back. No ratings or sales history exist yet, so no popularity/rating sort.
add_filter('woocommerce_catalog_orderby', static fn (): array => [
    'available' => __('Available first', 'schermo'),
    'date' => __('Newest', 'schermo'),
    'price' => __('Price: low to high', 'schermo'),
    'price-desc' => __('Price: high to low', 'schermo'),
]);
// Default: pieces that can be ordered now (priced and in stock) first, newest first within each group — so a listing
// never opens on a wall of "Coming soon".
add_filter('woocommerce_default_catalog_orderby', static fn (): string => 'available');
add_filter('woocommerce_get_catalog_ordering_args', static function (array $args, string $orderby): array {
    if ($orderby === 'available') {
        $args = ['orderby' => 'date ID', 'order' => 'DESC', 'meta_key' => ''];
        add_filter('posts_clauses', 'schermo_available_first_clauses', 30, 2);
    }
    return $args;
}, 10, 2);

function schermo_available_first_clauses(array $c, WP_Query $q): array
{
    global $wpdb;
    if (!$q->is_main_query() || str_contains((string) $c['join'], 'schermo_av')) {
        return $c;
    }
    $c['join'] .= " LEFT JOIN {$wpdb->wc_product_meta_lookup} schermo_av ON schermo_av.product_id = {$wpdb->posts}.ID";
    $c['orderby'] = "(schermo_av.max_price > 0 AND schermo_av.stock_status = 'instock') DESC, " . ($c['orderby'] ?: "{$wpdb->posts}.post_date DESC");
    return $c;
}
// When sorting by price, products without a price go last instead of first.
add_filter('posts_clauses', static function (array $c, WP_Query $q): array {
    if ($q->is_main_query() && str_contains((string) $c['orderby'], 'wc_product_meta_lookup.m')) {
        $c['orderby'] = '(wc_product_meta_lookup.max_price > 0) DESC, ' . $c['orderby'];
    }
    return $c;
}, 20, 2);

/**
 * What the current listing (before the drawer filters) contains, so the drawer only offers options with results:
 * sizes present, price range of priced products, whether anything is available to order, collections present.
 */
function schermo_shop_context(?WP_Term $current): array
{
    $f = schermo_filters();
    $cats = array_filter([$current ? $current->slug : '', $f['type'], $f['collection']]);
    $args = ['status' => 'publish', 'limit' => -1, 'return' => 'ids', 'visibility' => 'catalog'];
    if (is_search()) {
        $args['s'] = get_search_query();
    }
    $ids = wc_get_products($args);
    foreach ($cats as $slug) {
        $in = wc_get_products(array_merge($args, ['category' => [$slug]]));
        $ids = array_values(array_intersect($ids, $in));
    }
    $sizes = [];
    $order = ['s', 'm', 'l', 'xl', 'xxl', 'xxxl'];
    if ($ids && taxonomy_exists('pa_size')) {
        foreach (wp_get_object_terms($ids, 'pa_size') as $t) {
            $sizes[$t->slug] = $t->name;
        }
        uksort($sizes, static fn ($a, $b) => ((($i = array_search($a, $order, true)) === false ? 99 : $i) <=> (($j = array_search($b, $order, true)) === false ? 99 : $j)));
    }
    $range = null;
    if ($ids) {
        global $wpdb;
        $in = implode(',', array_map('intval', $ids));
        $row = $wpdb->get_row("SELECT MIN(NULLIF(min_price, 0)) lo, MAX(max_price) hi FROM {$wpdb->wc_product_meta_lookup} WHERE product_id IN ($in) AND max_price > 0"); // phpcs:ignore WordPress.DB
        if ($row && $row->hi) {
            $range = [(int) floor((float) $row->lo), (int) ceil((float) $row->hi)];
        }
    }
    $colls = [];
    foreach (schermo_collection_labels() as $slug => $label) {
        $t = get_term_by('slug', $slug, 'product_cat');
        if ($t && $ids && array_intersect($ids, wc_get_products(array_merge($args, ['category' => [$slug]])))) {
            $colls[$slug] = $label;
        }
    }
    return [
        'sizes' => $sizes,
        'range' => $range,
        'available' => (bool) array_intersect($ids, schermo_available_ids()),
        'collections' => $colls,
    ];
}

/** Active filter chips: [label, url that removes just this filter]. */
function schermo_filter_chips(): array
{
    $f = schermo_filters();
    $chips = [];
    if ($f['type'] !== '') {
        $chips[] = [schermo_types()[$f['type']], remove_query_arg(['type', 'paged'])];
    }
    if ($f['collection'] !== '') {
        $chips[] = [schermo_collection_labels()[$f['collection']], remove_query_arg(['collection', 'paged'])];
    }
    foreach ($f['sizes'] as $s) {
        $rest = array_diff($f['sizes'], [$s]);
        $url = $rest ? add_query_arg(['filter_size' => implode(',', $rest), 'query_type_size' => 'or'], remove_query_arg('paged')) : remove_query_arg(['filter_size', 'query_type_size', 'paged']);
        $chips[] = [sprintf(__('Size %s', 'schermo'), strtoupper($s)), $url];
    }
    if ($f['min'] !== null || $f['max'] !== null) {
        $label = $f['min'] !== null && $f['max'] !== null ? sprintf('₹%s–₹%s', number_format_i18n($f['min']), number_format_i18n($f['max']))
            : ($f['min'] !== null ? sprintf(__('From ₹%s', 'schermo'), number_format_i18n($f['min'])) : sprintf(__('Up to ₹%s', 'schermo'), number_format_i18n($f['max'])));
        $chips[] = [$label, remove_query_arg(['min_price', 'max_price', 'paged'])];
    }
    if ($f['available']) {
        $chips[] = [__('Available to order', 'schermo'), remove_query_arg(['available', 'paged'])];
    }
    return $chips;
}

/** The listing's own URL with every filter removed (sorting and search are kept). */
function schermo_clear_filters_url(): string
{
    return remove_query_arg(['type', 'collection', 'filter_size', 'query_type_size', 'min_price', 'max_price', 'available', 'paged']);
}

// The filter form submits sizes as filter_size[] and may send empty fields. Turn that into WooCommerce's own clean
// URL format (filter_size=m,l&query_type_size=or) so results, chips and shared links all match.
add_action('template_redirect', static function (): void {
    if (!(is_shop() || is_product_taxonomy() || (is_search() && get_query_var('post_type') === 'product'))) {
        return;
    }
    // phpcs:disable WordPress.Security.NonceVerification
    $get = wp_unslash($_GET);
    $dirty = false;
    if (isset($get['filter_size']) && is_array($get['filter_size'])) {
        $get['filter_size'] = implode(',', array_map('sanitize_title', $get['filter_size']));
        $dirty = true;
    }
    foreach (['type', 'collection', 'min_price', 'max_price', 'available', 'filter_size'] as $k) {
        if (array_key_exists($k, $get) && $get[$k] === '') {
            unset($get[$k]);
            $dirty = true;
        }
    }
    if (!empty($get['filter_size']) && empty($get['query_type_size'])) {
        $get['query_type_size'] = 'or';
        $dirty = true;
    }
    if (empty($get['filter_size']) && isset($get['query_type_size'])) {
        unset($get['query_type_size']);
        $dirty = true;
    }
    // phpcs:enable
    if ($dirty) {
        $base = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?');
        wp_safe_redirect(add_query_arg(array_map('rawurlencode', $get), home_url($base)), 302);
        exit;
    }
});
