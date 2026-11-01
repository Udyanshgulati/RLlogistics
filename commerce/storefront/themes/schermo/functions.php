<?php
/**
 * Schermo child theme: brand tokens, fonts and the homepage. Everything else comes from Brandhouse.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

const SCHERMO_VERSION = '0.2.0';

add_action('wp_enqueue_scripts', static function (): void {
    $uri = get_stylesheet_directory_uri();
    wp_enqueue_style('schermo', $uri . '/assets/css/schermo.css', ['brandhouse'], SCHERMO_VERSION);
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
 * Hero slides: product cut-outs with feature labels taken from what is visible on each product.
 * Words are the collection's own lines (Sempre Avanti, Play Beyond) or the product's name. A slide is
 * skipped if its product is not in the store. Spots: [label, x%, y%, side of the label].
 */
function schermo_hero_slides(): array
{
    if (!class_exists('WooCommerce')) {
        return [];
    }
    $dir = get_stylesheet_directory_uri() . '/assets/img/hero/';
    $defs = [
        ['MI-001', 'playing-jersey.webp', ['Sempre', 'Avanti'], [[__('Gold-trimmed collar', 'schermo'), 50, 11, 'left'], [__('Milan Icons crest', 'schermo'), 69, 33, 'right'], [__('Duomo skyline print', 'schermo'), 40, 78, 'left']]],
        ['MI-004', 'practice-kit.webp', ['Practice', 'Kit'], [[__('Gold-striped collar', 'schermo'), 51, 7, 'left'], [__('Milan Icons crest', 'schermo'), 67, 33, 'right'], [__('Tonal Duomo print', 'schermo'), 33, 72, 'left']]],
        ['MI-005', 'practice-kit-sleeveless.webp', ['Play', 'Beyond'], [[__('Gold-trimmed armholes', 'schermo'), 11, 26, 'left'], [__('Milan Icons crest', 'schermo'), 74, 30, 'right'], [__('Tonal Duomo print', 'schermo'), 48, 76, 'left']]],
        ['MI-006', 'practice-shorts.webp', ['Milan', 'Icons'], [[__('Drawstring waist', 'schermo'), 49, 24, 'right'], [__('Gold side piping', 'schermo'), 92, 49, 'right'], [__('Milan Icons crest', 'schermo'), 23, 80, 'left']]],
    ];
    $slides = [];
    foreach ($defs as [$sku, $img, $words, $spots]) {
        $id = wc_get_product_id_by_sku($sku);
        $p = $id ? wc_get_product($id) : null;
        if (!$p || $p->get_status() !== 'publish') {
            continue;
        }
        $coll = get_term_by('slug', 'milan-icons', 'product_cat');
        $slides[] = [
            'name' => $p->get_name(),
            'url' => $p->get_permalink(),
            'price' => $p->get_price_html(),
            'collection' => $coll ? $coll->name : '',
            'image' => $dir . $img,
            'words' => $words,
            'spots' => $spots,
        ];
    }
    return $slides;
}

/** Category tile image: the category's own thumbnail, else the newest product's photo in it. */
function schermo_category_image(WP_Term $cat): string
{
    static $used = [];
    // A representative product per category (full cut-out), else the category's own image, else the newest product.
    $featured = ['accessories' => 'MI-010', 'clothing' => 'MI-018', 'fan-merch' => 'MI-028', 'milan-icons' => 'MI-001', 'sportswear' => 'MI-014'];
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
    if (is_front_page()) {
        wp_enqueue_script('schermo-home', get_stylesheet_directory_uri() . '/assets/js/schermo.js', [], SCHERMO_VERSION, ['strategy' => 'defer', 'in_footer' => true]);
        wp_localize_script('schermo-home', 'schermoHero', array_map(static fn ($s) => ['name' => $s['name'], 'url' => $s['url'], 'price' => wp_strip_all_tags($s['price'])], schermo_hero_slides()));
    }
}, 21);

// Show the whole collection on one page (25 pieces) instead of spilling one product onto page 2.
add_filter('loop_shop_per_page', static fn (): int => 48, 20);
