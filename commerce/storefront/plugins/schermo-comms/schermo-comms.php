<?php
/**
 * Plugin Name: Schermo messages & reviews
 * Description: Customer communication (email + WhatsApp) with consent records, a reliable message queue, order and
 * marketing triggers, and verified product reviews from delivered orders. Starts in DRY-RUN mode: nothing is sent
 * until the owner connects providers and switches to live in WooCommerce → Schermo messages.
 * Version: 1.0.0
 * Requires Plugins: woocommerce
 */

defined('ABSPATH') || exit;

const SCM_VERSION = '1.0.0';
const SCM_DB = 3;
define('SCM_DIR', __DIR__);

require_once SCM_DIR . '/includes/consent.php';
require_once SCM_DIR . '/includes/providers.php';
require_once SCM_DIR . '/includes/queue.php';
require_once SCM_DIR . '/includes/templates.php';
require_once SCM_DIR . '/includes/triggers.php';
require_once SCM_DIR . '/includes/reviews.php';
require_once SCM_DIR . '/includes/admin.php';

/** Tables: consent records, the message log/queue, and review invitations. Created/updated on version change. */
function scm_install(): void
{
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $c = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE {$wpdb->prefix}scm_consents (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        contact varchar(190) NOT NULL,
        channel varchar(16) NOT NULL,
        purpose varchar(16) NOT NULL,
        status varchar(12) NOT NULL,
        source varchar(40) NOT NULL,
        wording_version varchar(20) NOT NULL DEFAULT '',
        wording text NULL,
        user_id bigint unsigned NOT NULL DEFAULT 0,
        order_id bigint unsigned NOT NULL DEFAULT 0,
        given_at datetime NULL,
        withdrawn_at datetime NULL,
        withdrawn_via varchar(40) NOT NULL DEFAULT '',
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY contact_channel_purpose (contact, channel, purpose)
    ) $c;");
    dbDelta("CREATE TABLE {$wpdb->prefix}scm_messages (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        idem varchar(190) NOT NULL,
        event varchar(40) NOT NULL,
        channel varchar(16) NOT NULL,
        purpose varchar(16) NOT NULL,
        template varchar(80) NOT NULL,
        template_version varchar(20) NOT NULL DEFAULT '',
        recipient varchar(190) NOT NULL,
        order_id bigint unsigned NOT NULL DEFAULT 0,
        vars longtext NULL,
        status varchar(16) NOT NULL,
        attempts smallint unsigned NOT NULL DEFAULT 0,
        provider_id varchar(190) NOT NULL DEFAULT '',
        result text NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY idem (idem),
        KEY status (status),
        KEY order_id (order_id)
    ) $c;");
    dbDelta("CREATE TABLE {$wpdb->prefix}scm_review_invites (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        token_hash char(64) NOT NULL,
        order_id bigint unsigned NOT NULL,
        item_id bigint unsigned NOT NULL,
        product_id bigint unsigned NOT NULL,
        review_id bigint unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        expires_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY item_id (item_id),
        UNIQUE KEY token_hash (token_hash)
    ) $c;");
    update_option('scm_db', SCM_DB, false);
}
add_action('plugins_loaded', static function (): void {
    if ((int) get_option('scm_db') < SCM_DB) {
        scm_install();
    }
});
register_activation_hook(__FILE__, 'scm_install');

/** Plugin settings with safe defaults: dry-run, every flow off for live sending until the owner turns it on. */
function scm_settings(): array
{
    $defaults = [
        'mode' => 'dry-run',              // 'dry-run' logs only; 'live' really sends (needs providers + approved templates)
        'review_moderation' => 1,         // customer reviews wait for approval before they appear
        'flows' => [],                    // "event:channel" => 1 when enabled
        'wa_templates' => [],             // event => ['name' => approved template name, 'lang' => 'en', 'category' => 'utility|marketing']
        'cart_reminder_hours' => 3,
    ];
    $s = get_option('scm_settings', []);
    return array_replace($defaults, is_array($s) ? $s : []);
}

function scm_flow_on(string $event, string $channel): bool
{
    $s = scm_settings();
    return !empty($s['flows']["{$event}:{$channel}"]);
}

function scm_now(): string
{
    return current_time('mysql', true);
}

/** Short, signed, expiring token for links such as unsubscribe (no personal data in the URL). */
function scm_sign(array $data, int $ttl_days = 365): string
{
    $data['x'] = time() + $ttl_days * DAY_IN_SECONDS;
    $p = rtrim(strtr(base64_encode((string) wp_json_encode($data)), '+/', '-_'), '=');
    return $p . '.' . substr(hash_hmac('sha256', $p, wp_salt('auth') . 'scm'), 0, 32);
}

function scm_verify(string $token): ?array
{
    [$p, $sig] = array_pad(explode('.', $token, 2), 2, '');
    if ($p === '' || !hash_equals(substr(hash_hmac('sha256', $p, wp_salt('auth') . 'scm'), 0, 32), $sig)) {
        return null;
    }
    $data = json_decode((string) base64_decode(strtr($p, '-_', '+/')), true);
    return is_array($data) && ($data['x'] ?? 0) > time() ? $data : null;
}
