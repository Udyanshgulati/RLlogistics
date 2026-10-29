<?php
/**
 * Schermo child theme: brand tokens, fonts and the homepage. Everything else comes from Brandhouse.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

const SCHERMO_VERSION = '0.1.0';

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
add_filter('brandhouse_icon_paths', static fn (array $paths): array => array_merge($paths, [
    'search' => '<polygon points="10 3,15 3,17 5,17 10,15 14,10 16,5 14,3 10,3 5,10 3"/><line x1="14.5" y1="13.5" x2="21" y2="20" stroke-width="2"/>',
    'user' => '<polygon points="12 3,15 5,15 8,12 10,9 8,9 5,12 3"/><path d="M4 21V18L7 14H17L20 18V21"/>',
    'bag' => '<path d="M4 8H20L19 19L17 21H7L5 19L4 8Z"/><path d="M8.5 8V5.5L10 4H14L15.5 5.5V8"/>',
    'menu' => '<line x1="3" y1="6" x2="21" y2="6" stroke-width="2"/><line x1="3" y1="12" x2="16" y2="12" stroke-width="2"/><line x1="3" y1="18" x2="11" y2="18" stroke-width="2"/>',
    'close' => '<line x1="5" y1="5" x2="19" y2="19" stroke-width="2"/><line x1="19" y1="5" x2="5" y2="19" stroke-width="2"/>',
    'arrow' => '<line x1="3" y1="12" x2="20" y2="12" stroke-width="2"/><path d="M13 5L20 12L13 19" stroke-width="2"/>',
    'truck' => '<path d="M2 6H14V17H2V6Z"/><path d="M14 10H18L21 13V17H14"/><polygon points="5 17,7 17,8 18,8 20,7 21,5 21,4 20,4 18"/><polygon points="16 17,18 17,19 18,19 20,18 21,16 21,15 20,15 18"/>',
    'return' => '<path d="M8 8L3 13L8 18" stroke-width="2"/><path d="M4 13H19L21 11V8L19 6H13"/>',
    'lock' => '<path d="M7 10V6.5L8.5 5H15.5L17 6.5V10"/><polygon points="5 10,19 10,20 11,20 20,19 21,5 21,4 20,4 11,5 10"/><line x1="12" y1="13" x2="12" y2="16" stroke-width="2"/>',
    'globe' => '<polygon points="8 3,16 3,21 8,21 16,16 21,8 21,3 16,3 8"/><line x1="3" y1="12" x2="21" y2="12"/><polygon points="12 3,16 8,16 16,12 21,8 16,8 8"/>',
    'filter' => '<path d="M3 4H21L14 13V19L10 21V13L3 4Z"/>',
    'heart' => '<polygon points="12 7,15 4,19 4,21 6,21 10,18 14,12 21,6 14,3 10,3 6,5 4,9 4,12 7"/>',
    'plus' => '<line x1="12" y1="4" x2="12" y2="20" stroke-width="2"/><line x1="4" y1="12" x2="20" y2="12" stroke-width="2"/>',
    'minus' => '<line x1="4" y1="12" x2="20" y2="12" stroke-width="2.5"/>',
    'ruler' => '<polygon points="4 16,16 4,20 8,8 20"/><line x1="8" y1="12" x2="10" y2="14" stroke-width="1.5"/><line x1="11" y1="9" x2="14" y2="12"/><line x1="14" y1="6" x2="16" y2="8" stroke-width="1.5"/>',
    'bell' => '<line x1="12" y1="3" x2="12" y2="5" stroke-width="2"/><path d="M6 17H18L17 10L14 6H10L7 10L6 17Z"/><line x1="4" y1="17" x2="20" y2="17" stroke-width="2"/><polygon points="10 19,14 19,13 21,11 21"/>',
]));
