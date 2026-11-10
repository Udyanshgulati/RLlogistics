<?php
/**
 * Team roles. Capabilities: tl_view (see the hub), tl_adjust (change stock), tl_fulfil (pack / dispatch orders),
 * tl_returns (record returns), tl_manage (stores, connection, settings). Administrators and shop managers get all.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

const TLI_CAPS = ['tl_view', 'tl_adjust', 'tl_fulfil', 'tl_returns', 'tl_manage'];

function tli_install_roles(): void
{
    foreach (['administrator', 'shop_manager'] as $r) {
        if ($role = get_role($r)) {
            foreach (TLI_CAPS as $c) {
                $role->add_cap($c);
            }
        }
    }
    remove_role('tl_warehouse');
    remove_role('tl_support');
    add_role('tl_warehouse', 'Warehouse staff', ['read' => true, 'tl_view' => true, 'tl_adjust' => true, 'tl_fulfil' => true, 'tl_returns' => true]);
    add_role('tl_support', 'Customer support', ['read' => true, 'tl_view' => true, 'tl_returns' => true]);
}

// Warehouse and support staff land on the hub, not the WordPress dashboard, and see nothing else.
add_filter('login_redirect', static function ($to, $req, $user) {
    return $user instanceof WP_User && array_intersect(['tl_warehouse', 'tl_support'], $user->roles) ? admin_url('admin.php?page=tl-inventory') : $to;
}, 10, 3);
add_action('admin_menu', static function (): void {
    $u = wp_get_current_user();
    if (array_intersect(['tl_warehouse', 'tl_support'], $u->roles)) {
        remove_menu_page('index.php');
        remove_menu_page('profile.php');
    }
}, 999);
add_action('admin_page_access_denied', static function (): void {
    if (array_intersect(['tl_warehouse', 'tl_support'], wp_get_current_user()->roles)) {
        wp_safe_redirect(admin_url('admin.php?page=tl-inventory'));
        exit;
    }
});
add_action('load-index.php', static function (): void {
    if (array_intersect(['tl_warehouse', 'tl_support'], wp_get_current_user()->roles)) {
        wp_safe_redirect(admin_url('admin.php?page=tl-inventory'));
        exit;
    }
});

// WooCommerce keeps users without editing rights out of wp-admin; hub team members need it.
add_filter('woocommerce_prevent_admin_access', static fn ($prevent) => current_user_can('tl_view') ? false : $prevent);
add_filter('woocommerce_disable_admin_bar', static fn ($disable) => current_user_can('tl_view') ? false : $disable);
add_filter('woocommerce_login_redirect', static function ($to, $user) {
    return $user instanceof WP_User && array_intersect(['tl_warehouse', 'tl_support'], $user->roles) ? admin_url('admin.php?page=tl-inventory') : $to;
}, 10, 2);
