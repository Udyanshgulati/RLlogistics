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
