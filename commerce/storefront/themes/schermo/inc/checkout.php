<?php
/**
 * Checkout presentation (WooCommerce block checkout). Only supported extension points are used: render_block to
 * add markup around blocks, and a small script for the step indicator and field hints. Totals, taxes, shipping,
 * coupons, validation, payment UI and order creation stay WooCommerce's and the payment provider's.
 */

defined('ABSPATH') || exit;

add_action('wp_enqueue_scripts', static function (): void {
    if (function_exists('is_checkout') && is_checkout() && !is_order_received_page()) {
        wp_enqueue_script('schermo-checkout', get_stylesheet_directory_uri() . '/assets/js/checkout.js', [], brandhouse_asset_ver('/assets/js/checkout.js', true), ['strategy' => 'defer', 'in_footer' => true]);
    }
}, 30);

add_filter('body_class', static function (array $c): array {
    if (function_exists('is_checkout') && is_checkout()) {
        $c[] = is_order_received_page() ? 'sch-thankyou' : 'sch-checkout';
    }
    return $c;
});

// Steps for the real sections of this one-page checkout, and a way back to the bag (entered details are kept in
// the customer session by WooCommerce, so nothing is lost).
add_filter('render_block', static function (string $html, array $block): string {
    if (($block['blockName'] ?? '') !== 'woocommerce/checkout' || is_order_received_page()) {
        return $html;
    }
    $steps = [
        'contact' => __('Contact', 'schermo'),
        'delivery' => __('Delivery', 'schermo'),
        'payment' => __('Payment', 'schermo'),
    ];
    $out = '<div class="ck-bar"><nav class="ck-steps" aria-label="' . esc_attr__('Checkout steps', 'schermo') . '"><ol>';
    $i = 0;
    foreach ($steps as $key => $label) {
        $out .= sprintf('<li data-ck-step="%1$s"><a href="#ck-%1$s"><span class="ck-n" aria-hidden="true">%2$d</span><span class="ck-l">%3$s</span><span class="sr-only ck-sr"></span></a></li>', esc_attr($key), ++$i, esc_html($label));
    }
    $out .= '</ol></nav><a class="ck-edit" href="' . esc_url(wc_get_cart_url()) . '">' . esc_html__('Edit bag', 'schermo') . '</a></div>';
    return $out . $html;
}, 10, 2);
