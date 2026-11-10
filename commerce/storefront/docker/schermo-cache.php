<?php
/**
 * Plugin Name: Schermo page cache helpers
 * Description: Clears the page cache when content changes and marks pages that must never be cached.
 */
defined('ABSPATH') || exit;

function schermo_cache_clear(): void
{
    foreach (glob(WP_CONTENT_DIR . '/cache/schermo-page/*.html') ?: [] as $f) {
        @unlink($f);
    }
}
foreach (['save_post', 'deleted_post', 'trashed_post', 'edited_term', 'created_term', 'delete_term', 'wp_update_nav_menu', 'customize_save_after',
    'woocommerce_update_product', 'woocommerce_product_set_stock', 'woocommerce_variation_set_stock', 'woocommerce_product_set_stock_status',
    'woocommerce_variation_set_stock_status', 'woocommerce_settings_saved', 'switch_theme'] as $hook) {
    add_action($hook, 'schermo_cache_clear');
}
add_action('template_redirect', static function (): void {
    if (is_user_logged_in() || is_search() || is_404() || (function_exists('is_woocommerce') && (is_cart() || is_checkout() || is_account_page()))) {
        defined('DONOTCACHEPAGE') || define('DONOTCACHEPAGE', true);
    }
}, 0);
