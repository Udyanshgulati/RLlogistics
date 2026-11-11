<?php
/**
 * Schermo child theme: brand tokens, fonts and the homepage. Everything else comes from Brandhouse.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

const SCHERMO_VERSION = '0.2.0';

require_once __DIR__ . '/inc/shop-filters.php';
require_once __DIR__ . '/inc/product.php';
require_once __DIR__ . '/inc/checkout.php';
require_once __DIR__ . '/inc/engage.php';

add_action('wp_enqueue_scripts', static function (): void {
    $uri = get_stylesheet_directory_uri();
    wp_enqueue_style('schermo', $uri . '/assets/css/schermo.css', ['brandhouse'], brandhouse_asset_ver('/assets/css/schermo.css', true));
}, 20);

// Preload the two fonts used above the fold.
add_action('wp_head', static function (): void {
    $uri = get_stylesheet_directory_uri() . '/assets/fonts/';
    foreach (['syncopate-700-latin.woff2', 'inter-var-latin.woff2'] as $f) {
        printf('<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url($uri . $f));
    }
}, 2);

/** Image URL from a Customizer image setting, or '' when the client has not supplied one. */
function schermo_image(string $key): string
{
    $id = (int) get_theme_mod($key, 0);
    return $id ? (string) wp_get_attachment_image_url($id, 'full') : '';
}

// Homepage content is client-supplied. Until then each slot shows a labelled placeholder.
add_action('customize_register', static function (WP_Customize_Manager $c): void {
    $c->add_section('schermo_home', ['title' => __('Homepage', 'schermo'), 'priority' => 30]);
    $text = [
        'schermo_hero_title' => [__('Hero headline', 'schermo'), 'text'],
        'schermo_hero_text' => [__('Hero line', 'schermo'), 'text'],
        'schermo_hero_cta' => [__('Hero button label', 'schermo'), 'text'],
        'schermo_story_title' => [__('Brand story heading', 'schermo'), 'text'],
        'schermo_story_text' => [__('Brand story text', 'schermo'), 'textarea'],
    ];
    foreach ($text as $id => [$label, $type]) {
        $c->add_setting($id, ['sanitize_callback' => $type === 'textarea' ? 'sanitize_textarea_field' : 'sanitize_text_field']);
        $c->add_control($id, ['label' => $label, 'section' => 'schermo_home', 'type' => $type]);
    }
    foreach (['schermo_hero_image' => __('Hero image', 'schermo'), 'schermo_story_image' => __('Brand story image', 'schermo')] as $id => $label) {
        $c->add_setting($id, ['sanitize_callback' => 'absint']);
        $c->add_control(new WP_Customize_Media_Control($c, $id, ['label' => $label, 'section' => 'schermo_home', 'mime_type' => 'image']));
    }
});

/** Inline SVG from the Schermo logo files (extracted from Schermo_Logo.ai), decorative unless labelled by the caller. */
function schermo_svg(string $name, string $class = ''): string
{
    static $cache = [];
    $cache[$name] ??= (string) file_get_contents(get_stylesheet_directory() . '/assets/img/' . $name . '.svg');
    return str_replace('<svg ', '<svg class="' . esc_attr($class) . '" aria-hidden="true" focusable="false" ', $cache[$name]);
}

// Header and drawer: the wordmark. Footer: the stacked logo (S mark above the wordmark).
add_filter('brandhouse_logo_svg', static fn (string $svg, string $where): string => $where === 'footer'
    ? schermo_svg('schermo-logo', 'logo-svg')
    : schermo_svg('schermo-wordmark', 'logo-svg'), 10, 2);

// Large, faint S mark behind the footer.
add_action('brandhouse_footer_art', static function (): void {
    echo '<div class="foot-art">' . schermo_svg('schermo-mark') . '</div>';
});

// Favicon from the S mark, unless a Site Icon has been set in the Customizer.
add_action('wp_head', static function (): void {
    if (!has_site_icon()) {
        printf('<link rel="icon" type="image/svg+xml" href="%s">' . "\n", esc_url(get_stylesheet_directory_uri() . '/assets/img/favicon.svg'));
    }
});

/**
 * Schermo icon set, designed in Stitch to match the logo: square line ends, mitred corners, 45° cuts instead of
 * curves, and the same forward slant as the S (applied in schermo.css). These are the simplified 24px versions.
 */
// Standard, instantly recognisable icons from the parent theme (the slanted Stitch set was hard to read).
// Only the icons the parent lacks are added here.
add_filter('brandhouse_icon_paths', static fn (array $paths): array => array_merge([
    'ruler' => '<path d="M3 17 17 3l4 4L7 21z"/><path d="m7 13 2 2M10 10l2 2M13 7l2 2"/>',
    'bell' => '<path d="M6 16V11a6 6 0 0 1 12 0v5l2 2H4z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
    'heart' => '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/>',
], $paths));

/**
 * Hero slides: one product per collection, with feature labels taken from what is visible on each product.
 * Words are the collection's own lines from the owner's deck. A slide is skipped if its product is not in the store.
 * Image: a cut-out in assets/img/hero/, or null for the product's own first photo. Spots: [label, x%, y%, side].
 */
function schermo_hero_slides(): array
{
    if (!class_exists('WooCommerce')) {
        return [];
    }
    $dir = get_stylesheet_directory_uri() . '/assets/img/hero/';
    $defs = [
        ['SC-001', null, ['Sport', 'Street'], [[__('Appliqué No. 10', 'schermo'), 52, 56, 'right'], [__('Cream curved panels', 'schermo'), 22, 47, 'left'], [__('Contrast collar', 'schermo'), 50, 25, 'left']]],
        ['SA-003', null, ['Limited', 'Drops'], [[__('Raised chenille S', 'schermo'), 58, 38, 'right'], [__('Washed black body', 'schermo'), 30, 32, 'left'], [__('Maroon side panels', 'schermo'), 74, 72, 'right']]],
        ['MI-001', 'playing-jersey.webp', ['Sempre', 'Avanti'], [[__('Gold-trimmed collar', 'schermo'), 50, 11, 'left'], [__('Milan Icons crest', 'schermo'), 69, 33, 'right'], [__('Duomo skyline print', 'schermo'), 40, 78, 'left']]],
        ['GC-001', null, ['Glasgow', 'Cosmic'], [[__('Glasgow Cosmic crest', 'schermo'), 64, 30, 'right'], [__('Woven thistle pattern', 'schermo'), 38, 64, 'left'], [__('Electric blue panels', 'schermo'), 79, 74, 'right']]],
    ];
    $names = array_column(schermo_collections(), 'label', 'slug');
    $slides = [];
    foreach ($defs as [$sku, $img, $words, $spots]) {
        $id = wc_get_product_id_by_sku($sku);
        $p = $id ? wc_get_product($id) : null;
        if (!$p || $p->get_status() !== 'publish') {
            continue;
        }
        $src = $img ? $dir . $img : (string) wp_get_attachment_image_url((int) $p->get_image_id(), 'full');
        if ($src === '') {
            continue;
        }
        $coll = '';
        $coll_slug = '';
        foreach (wp_get_post_terms($id, 'product_cat', ['fields' => 'slugs']) as $slug) {
            if (isset($names[$slug])) {
                $coll = $names[$slug];
                $coll_slug = $slug;
            }
        }
        $slides[] = [
            'name' => $p->get_name(),
            'url' => $p->get_permalink(),
            'price' => $p->get_price_html(),
            'collection' => $coll,
            'image' => $src,
            'words' => $words,
            'spots' => $spots,
            'ready' => $p->is_purchasable() && $p->is_in_stock(),
            // Orderable pieces get "Shop now" to the product; others are a preview of their collection.
            'coll_name' => ($ct = $coll_slug ? get_term_by('slug', $coll_slug, 'product_cat') : null) ? $ct->name : $coll,
            'cta' => sprintf($p->is_purchasable() && $p->is_in_stock() ? __('Shop %s', 'schermo') : __('Preview %s', 'schermo'), $ct ? $ct->name : __('the collection', 'schermo')),
            'cta_url' => $ct ? get_term_link($ct) : $p->get_permalink(),
        ];
    }
    // Pieces that can be ordered now lead; the rest keep their order.
    usort($slides, static fn ($a, $b) => (int) $b['ready'] <=> (int) $a['ready']);
    return $slides;
}

/**
 * The four collections in deck order, with the deck's subtitle and line, a representative product photo and, for
 * the team collections, the crest. Collections not yet in the store are left out.
 */
function schermo_collections(): array
{
    static $out = null;
    if ($out !== null || !class_exists('WooCommerce')) {
        return $out ?? [];
    }
    $img = get_stylesheet_directory_uri() . '/assets/img/';
    $defs = [
        ['schermo-core', 'core', __('Core', 'schermo'), __('Schermo Essentials', 'schermo'), __('Tees, baggy trousers, caps, backpacks, shorts.', 'schermo'), null],
        ['schermo-atelier', 'atelier', __('Atelier', 'schermo'), __('Limited drops', 'schermo'), __('Embroidery, patchwork and hand stitched detail.', 'schermo'), null],
        ['milan-icons', 'icons', __('Milan Icons', 'schermo'), __('Team merchandise', 'schermo'), __('Black and gold, drawn from the Duomo.', 'schermo'), $img . 'crest-milan-icons.webp'],
        ['glasgow-cosmic', 'cosmic', __('Glasgow Cosmic', 'schermo'), __('Team merchandise', 'schermo'), __('Cosmic navy, electric blue, Scottish thistle.', 'schermo'), $img . 'crest-glasgow-cosmic.webp'],
    ];
    $out = [];
    foreach ($defs as [$slug, $key, $label, $sub, $line, $crest]) {
        $term = get_term_by('slug', $slug, 'product_cat');
        if (!$term || is_wp_error($term) || !$term->count) {
            continue;
        }
        $ids = wc_get_products(['status' => 'publish', 'limit' => -1, 'return' => 'ids', 'category' => [$slug]]);
        $ready = function_exists('schermo_available_ids') ? count(array_intersect($ids, schermo_available_ids())) : 0;
        $out[] = ['slug' => $slug, 'key' => $key, 'term' => $term, 'label' => $label, 'sub' => $sub, 'line' => $line, 'crest' => $crest, 'image' => schermo_category_image($term), 'ready' => $ready];
    }
    return $out;
}

/** Category tile image: the category's own thumbnail, else the newest product's photo in it. */
function schermo_category_image(WP_Term $cat): string
{
    static $used = [];
    // A representative product per category (full cut-out), else the category's own image, else the newest product.
    $featured = ['accessories' => 'SC-028', 'clothing' => 'SC-016', 'fan-merch' => 'GC-026', 'sportswear' => 'MI-014', 'schermo-core' => 'SC-001', 'schermo-atelier' => 'SA-003', 'milan-icons' => 'MI-001', 'glasgow-cosmic' => 'GC-001'];
    $thumb = 0;
    if (isset($featured[$cat->slug]) && ($fid = wc_get_product_id_by_sku($featured[$cat->slug])) && get_post_status($fid) === 'publish') {
        $thumb = (int) get_post_thumbnail_id($fid);
    }
    $thumb = $thumb ?: (int) get_term_meta($cat->term_id, 'thumbnail_id', true);
    if (!$thumb) {
        foreach (wc_get_products(['category' => [$cat->slug], 'limit' => 12, 'status' => 'publish', 'return' => 'ids', 'orderby' => 'date', 'order' => 'DESC']) as $pid) {
            $t = (int) get_post_thumbnail_id($pid);
            if ($t && !in_array($t, $used, true)) {
                $thumb = $t;
                break;
            }
        }
    }
    $used[] = $thumb;
    return $thumb ? (string) wp_get_attachment_image($thumb, 'woocommerce_thumbnail', false, ['loading' => 'lazy']) : '';
}

add_action('wp_enqueue_scripts', static function (): void {
    if (is_shop() || is_product_taxonomy() || (is_search() && get_query_var('post_type') === 'product')) {
        wp_enqueue_script('schermo-shop', get_stylesheet_directory_uri() . '/assets/js/shop.js', [], brandhouse_asset_ver('/assets/js/shop.js', true), ['strategy' => 'defer', 'in_footer' => true]);
    }
    if (is_front_page()) {
        wp_enqueue_script('schermo-home', get_stylesheet_directory_uri() . '/assets/js/schermo.js', [], brandhouse_asset_ver('/assets/js/schermo.js', true), ['strategy' => 'defer', 'in_footer' => true]);
        wp_localize_script('schermo-home', 'schermoHero', array_map(static fn ($s) => ['name' => $s['name'], 'url' => $s['url'], 'cta' => $s['cta'], 'ctaUrl' => $s['cta_url'], 'ready' => (bool) $s['ready'], 'coll' => $s['coll_name'], 'price' => trim(wp_strip_all_tags(preg_replace('#<span class="screen-reader-text">.*?</span>#', '', $s['price'])))], schermo_hero_slides()));
    }
}, 21);

// Show the whole collection on one page (25 pieces) instead of spilling one product onto page 2.
add_filter('loop_shop_per_page', static fn (): int => 48, 20);

/** "Shop by category" tiles: the shopper-facing types that have products, each with a representative cut-out. */
function schermo_home_types(): array
{
    $pick = ['t-shirts-polos' => 'SC-001', 'trousers' => 'SC-016', 'shorts' => 'SC-045', 'caps-hats' => 'SC-029', 'bags-backpacks' => 'SC-037', 'sportswear' => 'MI-001'];
    $out = [];
    foreach ($pick as $slug => $sku) {
        $t = get_term_by('slug', $slug, 'product_cat');
        if (!$t || !$t->count) {
            continue;
        }
        $pid = wc_get_product_id_by_sku($sku);
        $img = $pid && get_post_status($pid) === 'publish' ? (string) wp_get_attachment_image((int) get_post_thumbnail_id($pid), 'woocommerce_thumbnail', false, ['loading' => 'lazy', 'alt' => '']) : '';
        $out[] = ['name' => $t->name, 'url' => get_term_link($t), 'count' => (int) $t->count, 'image' => $img ?: schermo_category_image($t)];
    }
    return $out;
}

/** Product IDs that can be ordered now (real price and stock), newest first. */
function schermo_home_available(int $limit): array
{
    $ids = wc_get_products(['status' => 'publish', 'limit' => -1, 'return' => 'ids', 'orderby' => 'date', 'order' => 'DESC']);
    return array_slice(array_values(array_intersect($ids, schermo_available_ids())), 0, $limit);
}

/**
 * Dropdown imagery. Collections (second level under "Collections"): an image card with the collection's own line and
 * whether it can be ordered now. Shop groups (second level under "Shop"): a small cut-out above each group, plus a
 * short line for groups without sub-types (Sportswear, Fan Merch) so no column is left empty. Images are decorative
 * (alt="") because the link text names the destination.
 */
function schermo_menu_media(): array
{
    static $m = null;
    if ($m !== null) {
        return $m;
    }
    $img = get_stylesheet_directory_uri() . '/assets/img/';
    $cut = static function (string $sku): string {
        $pid = wc_get_product_id_by_sku($sku);
        return $pid ? (string) wp_get_attachment_image_url((int) get_post_thumbnail_id($pid), 'woocommerce_thumbnail') : '';
    };
    $m = [
        'schermo-core' => ['img' => $cut('SC-006'), 'line' => __('Schermo Essentials', 'schermo'), 'kind' => 'cut'],
        'schermo-atelier' => ['img' => $cut('SA-003'), 'line' => __('Limited drops', 'schermo'), 'kind' => 'cut'],
        'milan-icons' => ['img' => $img . 'banner-milan-icons.webp', 'line' => __('Black and gold, from the Duomo', 'schermo'), 'kind' => 'photo'],
        'glasgow-cosmic' => ['img' => $img . 'banner-glasgow-cosmic.webp', 'line' => __('Cosmic navy, electric blue', 'schermo'), 'kind' => 'photo'],
        'clothing' => ['img' => $cut('SC-001'), 'line' => '', 'kind' => 'cut'],
        'accessories' => ['img' => $cut('SC-029'), 'line' => '', 'kind' => 'cut'],
        'sportswear' => ['img' => $cut('MI-001'), 'line' => '', 'kind' => 'cut', 'by_collection' => true],
        'fan-merch' => ['img' => $cut('GC-026'), 'line' => '', 'kind' => 'photo', 'by_collection' => true],
    ];
    if (function_exists('schermo_collections')) {
        foreach (schermo_collections() as $c) {
            if (isset($m[$c['slug']])) {
                $m[$c['slug']]['state'] = $c['ready'] ? sprintf(__('%d available now', 'schermo'), $c['ready']) : __('Coming soon', 'schermo');
            }
        }
    }
    return $m;
}

add_filter('walker_nav_menu_start_el', static function (string $html, $item, int $depth, $args): string {
    if ($depth !== 1 || ($item->object ?? '') !== 'product_cat' || ($args->theme_location ?? '') !== 'primary') {
        return $html;
    }
    $t = get_term((int) $item->object_id, 'product_cat');
    $m = $t instanceof WP_Term ? (schermo_menu_media()[$t->slug] ?? null) : null;
    if (!$m || $m['img'] === '') {
        return $html;
    }
    $media = '<span class="nm-media nm-' . esc_attr($m['kind']) . '" aria-hidden="true"><img src="' . esc_url($m['img']) . '" alt="" loading="lazy" decoding="async" width="300" height="375"></span>';
    $extra = ($m['line'] !== '' ? '<span class="nm-line">' . esc_html($m['line']) . '</span>' : '') . (!empty($m['state']) ? '<span class="nm-state">' . esc_html($m['state']) . '</span>' : '');
    $html = (string) preg_replace('#(<a\b[^>]*>)(.*?)(</a>)#s', '$1' . $media . '<span class="nm-text"><span class="nm-name">$2</span>' . str_replace('$', '\$', $extra) . '</span>$3', $html, 1);
    // Types without sub-types (Sportswear, Fan Merch): list the collections that have pieces of this type, as real
    // filtered views, so the column reads like the others instead of a description.
    if (!empty($m['by_collection']) && $t instanceof WP_Term) {
        $ids = wc_get_products(['status' => 'publish', 'limit' => -1, 'return' => 'ids', 'category' => [$t->slug]]);
        $links = '';
        foreach (schermo_collection_labels() as $slug => $label) {
            if ($ids && array_intersect($ids, wc_get_products(['status' => 'publish', 'limit' => -1, 'return' => 'ids', 'category' => [$slug]]))) {
                $links .= '<li class="menu-item"><a href="' . esc_url(add_query_arg('collection', $slug, get_term_link($t))) . '">' . esc_html($label) . '</a></li>';
            }
        }
        $links .= '<li class="menu-item"><a href="' . esc_url(get_term_link($t)) . '">' . esc_html(sprintf(__('All %s', 'schermo'), $t->name)) . '</a></li>';
        $html .= '<ul class="sub-menu nm-sub">' . $links . '</ul>';
    }
    return $html;
}, 5, 4);

// Prepaid only: Cash on Delivery is never offered, even if someone switches it on in WooCommerce.
add_filter('woocommerce_available_payment_gateways', static function ($gateways) {
    unset($gateways['cod']);
    return $gateways;
});
