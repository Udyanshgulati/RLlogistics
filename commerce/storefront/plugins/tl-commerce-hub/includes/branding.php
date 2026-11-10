<?php
/** Thoda Logic branding across WordPress admin and the login page. */

declare(strict_types=1);

defined('ABSPATH') || exit;

const TLI_BRAND = 'Thoda Logic';

function tli_brand_mark(int $size = 28): string
{
    return sprintf('<span class="tl-mark" style="width:%1$dpx;height:%1$dpx">TL</span>', $size);
}

// Login page.
add_action('login_enqueue_scripts', static function (): void {
    wp_enqueue_style('tli-brand', TLI_URL . 'assets/brand.css', [], TLI_VERSION);
});
add_filter('login_headerurl', static fn (): string => home_url('/'));
add_filter('login_headertext', static fn (): string => get_bloginfo('name') . ' · ' . TLI_BRAND);
add_action('login_message', static function ($m) {
    return '<div class="tl-login-brand">' . tli_brand_mark(44) . '<div><b>' . esc_html(get_bloginfo('name')) . ' admin</b><span>Managed by ' . TLI_BRAND . '</span></div></div>' . $m;
});

// Admin: colours, top bar, footer.
add_action('admin_enqueue_scripts', static fn () => wp_enqueue_style('tli-brand', TLI_URL . 'assets/brand.css', [], TLI_VERSION));
add_action('wp_enqueue_scripts', static function (): void {
    if (is_admin_bar_showing()) {
        wp_enqueue_style('tli-brand-bar', TLI_URL . 'assets/brand.css', [], TLI_VERSION);
    }
});
add_action('admin_bar_menu', static function (WP_Admin_Bar $bar): void {
    $bar->add_node(['id' => 'tl-brand', 'title' => '<span class="tl-bar-mark">TL</span><span class="tl-bar-name">' . TLI_BRAND . '</span>', 'href' => admin_url('admin.php?page=tl-inventory'), 'meta' => ['class' => 'tl-bar-brand']]);
}, 1);
add_action('admin_bar_menu', static fn (WP_Admin_Bar $bar) => $bar->remove_node('wp-logo'), 999);
add_filter('admin_footer_text', static fn (): string => '<span class="tl-footer">' . tli_brand_mark(18) . ' Built and managed by <b>' . TLI_BRAND . '</b></span>');
add_filter('update_footer', static fn (): string => 'TL Commerce Hub ' . TLI_VERSION, 11);
add_filter('admin_title', static fn ($t) => str_replace(['&lsaquo; ', ' &#8212; WordPress', ' — WordPress'], ['· ', ' · ' . TLI_BRAND, ' · ' . TLI_BRAND], $t));

// Dashboard: replace WordPress news widgets with a TL welcome card.
add_action('wp_dashboard_setup', static function (): void {
    remove_meta_box('dashboard_primary', 'dashboard', 'side');
    remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
    remove_action('welcome_panel', 'wp_welcome_panel');
    wp_add_dashboard_widget('tl_welcome', TLI_BRAND, static function (): void {
        $s = function_exists('tli_stock_summary') ? tli_stock_summary() : null;
        echo '<div class="tl-welcome">' . tli_brand_mark(36) . '<div><b>' . esc_html(get_bloginfo('name')) . '</b><p>Your store is managed by ' . TLI_BRAND . '.</p></div></div>';
        if ($s) {
            printf('<p>%d sizes in the catalogue · %d low · %d out of stock</p>', $s['skus'], $s['low'], $s['out']);
        }
        printf('<p><a class="button button-primary" href="%s">Open TL Commerce Hub</a> <a class="button" href="%s">Orders</a></p>', esc_url(admin_url('admin.php?page=tl-inventory')), esc_url(admin_url('admin.php?page=tl-orders')));
    });
    global $wp_meta_boxes;
    $w = $wp_meta_boxes['dashboard']['normal']['core']['tl_welcome'] ?? null;
    if ($w) {
        unset($wp_meta_boxes['dashboard']['normal']['core']['tl_welcome']);
        $wp_meta_boxes['dashboard']['normal']['high'] = ['tl_welcome' => $w] + ($wp_meta_boxes['dashboard']['normal']['high'] ?? []);
    }
});
