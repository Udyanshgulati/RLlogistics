<?php
/**
 * Plugin Name: TL Commerce Hub
 * Description: One central stock and order backend for several WooCommerce storefronts — inventory, stock movements, orders and fulfilment. By Thoda Logic.
 * Version: 1.1.0
 * Author: Thoda Logic
 * Author URI: https://thodalogic.com
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * Text Domain: tl-commerce-hub
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

const TLI_VERSION = '1.1.0';
const TLI_DB_VERSION = '2';
define('TLI_DIR', __DIR__);
define('TLI_URL', plugin_dir_url(__FILE__));

require_once TLI_DIR . '/includes/stock.php';
require_once TLI_DIR . '/includes/orders.php';
require_once TLI_DIR . '/includes/admin.php';
require_once TLI_DIR . '/includes/branding.php';
require_once TLI_DIR . '/includes/sync.php';
require_once TLI_DIR . '/includes/hub.php';
require_once TLI_DIR . '/includes/store.php';

register_activation_hook(__FILE__, 'tli_install');
add_action('admin_init', static function (): void {
    if (get_option('tli_db_version') !== TLI_DB_VERSION) {
        tli_install();
    }
});

/** Stock movement ledger: one row per change, never edited or deleted. */
function tli_install(): void
{
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $t = $wpdb->prefix . 'tl_stock_movements';
    dbDelta("CREATE TABLE {$t} (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        product_id bigint unsigned NOT NULL,
        sku varchar(100) NOT NULL DEFAULT '',
        qty_change int NOT NULL,
        qty_before int NULL,
        qty_after int NULL,
        reason varchar(32) NOT NULL,
        note varchar(255) NOT NULL DEFAULT '',
        order_id bigint unsigned NULL,
        user_id bigint unsigned NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY product_id (product_id),
        KEY created_at (created_at)
    ) {$wpdb->get_charset_collate()};");
    $s = $wpdb->prefix . 'tl_stores';
    dbDelta("CREATE TABLE {$s} (
        id varchar(32) NOT NULL,
        name varchar(100) NOT NULL,
        url varchar(255) NOT NULL,
        secret varchar(128) NOT NULL,
        active tinyint(1) NOT NULL DEFAULT 1,
        last_seen datetime NULL,
        last_error varchar(255) NOT NULL DEFAULT '',
        created_at datetime NOT NULL,
        PRIMARY KEY  (id)
    ) {$wpdb->get_charset_collate()};");
    $o = $wpdb->prefix . 'tl_remote_orders';
    dbDelta("CREATE TABLE {$o} (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        store_id varchar(32) NOT NULL,
        remote_id varchar(64) NOT NULL,
        number varchar(64) NOT NULL,
        status varchar(32) NOT NULL,
        stock_taken tinyint(1) NOT NULL DEFAULT 0,
        currency char(3) NOT NULL DEFAULT 'INR',
        total decimal(14,2) NOT NULL DEFAULT 0,
        country char(2) NOT NULL DEFAULT '',
        customer varchar(150) NOT NULL DEFAULT '',
        lines_json longtext NOT NULL,
        placed_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY store_remote (store_id, remote_id),
        KEY status (status)
    ) {$wpdb->get_charset_collate()};");
    update_option('tli_db_version', TLI_DB_VERSION);
    add_option('tli_mode', 'hub');
    add_option('tli_low_stock', 5);
}
