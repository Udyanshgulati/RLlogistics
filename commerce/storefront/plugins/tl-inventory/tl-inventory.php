<?php
/**
 * Plugin Name: TL Inventory
 * Description: Inventory, stock movements and order operations for WooCommerce — by Thoda Logic.
 * Version: 1.0.0
 * Author: Thoda Logic
 * Author URI: https://thodalogic.com
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * Text Domain: tl-inventory
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

const TLI_VERSION = '1.0.0';
const TLI_DB_VERSION = '1';
define('TLI_DIR', __DIR__);
define('TLI_URL', plugin_dir_url(__FILE__));

require_once TLI_DIR . '/includes/stock.php';
require_once TLI_DIR . '/includes/orders.php';
require_once TLI_DIR . '/includes/admin.php';
require_once TLI_DIR . '/includes/branding.php';

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
    update_option('tli_db_version', TLI_DB_VERSION);
    add_option('tli_low_stock', 5);
}
