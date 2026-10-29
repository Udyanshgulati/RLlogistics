<?php
/**
 * Brandhouse — shared storefront components. Brand tokens, fonts and homepage come from the child theme.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

const BRANDHOUSE_VERSION = '0.1.0';

require_once __DIR__ . '/inc/template-tags.php';
require_once __DIR__ . '/inc/woocommerce.php';
require_once __DIR__ . '/inc/markets.php';

add_action('after_setup_theme', static function (): void {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', ['height' => 120, 'width' => 400, 'flex-height' => true, 'flex-width' => true]);
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('responsive-embeds');
    add_theme_support('woocommerce', [
        'thumbnail_image_width' => 600,
        'single_image_width' => 1000,
        'product_grid' => ['default_columns' => 4, 'default_rows' => 4, 'min_columns' => 2, 'max_columns' => 4],
    ]);
    register_nav_menus([
        'primary' => __('Main menu', 'brandhouse'),
        'footer-shop' => __('Footer: shop', 'brandhouse'),
        'footer-help' => __('Footer: help', 'brandhouse'),
        'footer-legal' => __('Footer: legal', 'brandhouse'),
    ]);
});

add_action('wp_enqueue_scripts', static function (): void {
    $uri = get_template_directory_uri();
    wp_enqueue_style('brandhouse', $uri . '/assets/css/base.css', [], BRANDHOUSE_VERSION);
    wp_enqueue_script('brandhouse', $uri . '/assets/js/theme.js', [], BRANDHOUSE_VERSION, ['strategy' => 'defer', 'in_footer' => true]);
    wp_localize_script('brandhouse', 'brandhouse', ['sizeAttribute' => 'attribute_pa_size']);
});

// Lean front end: no emoji script, no oEmbed discovery on a shop.
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('wp_head', 'wp_oembed_add_discovery_links');

/** A clearly labelled, editable placeholder when the client has not supplied content yet. */
function brandhouse_mod(string $key, string $default): string
{
    $value = get_theme_mod($key, '');
    return $value !== '' ? $value : $default;
}

/** Until a menu is assigned: product categories, so the header is never empty or fake. */
function brandhouse_menu_fallback(): void
{
    if (!taxonomy_exists('product_cat')) {
        return;
    }
    $terms = get_terms(['taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => true, 'exclude' => [(int) get_option('default_product_cat')]]);
    echo '<ul class="menu">';
    if (function_exists('wc_get_page_permalink')) {
        echo '<li><a href="' . esc_url(wc_get_page_permalink('shop')) . '">' . esc_html__('Shop all', 'brandhouse') . '</a></li>';
    }
    foreach (is_array($terms) ? $terms : [] as $t) {
        echo '<li><a href="' . esc_url(get_term_link($t)) . '">' . esc_html($t->name) . '</a></li>';
    }
    echo '</ul>';
}

add_action('customize_register', static function (WP_Customize_Manager $c): void {
    $c->add_section('brandhouse_general', ['title' => __('Store text', 'brandhouse'), 'priority' => 150]);
    foreach (['brandhouse_announcement' => __('Announcement bar (empty = hidden)', 'brandhouse'), 'brandhouse_footer_note' => __('Footer note', 'brandhouse')] as $id => $label) {
        $c->add_setting($id, ['sanitize_callback' => 'sanitize_text_field']);
        $c->add_control($id, ['label' => $label, 'section' => 'brandhouse_general', 'type' => 'text']);
    }
});
