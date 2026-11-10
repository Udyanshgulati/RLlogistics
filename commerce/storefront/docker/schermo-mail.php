<?php
/**
 * Plugin Name: Schermo email delivery
 * Description: Sends all WordPress / WooCommerce email through an SMTP mail service set in the server's env file
 * (SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_SECURE=tls|ssl, SMTP_FROM, SMTP_FROM_NAME). The container has
 * no local mail system, so without these settings no email leaves the site.
 */
defined('ABSPATH') || exit;

function schermo_mail_ready(): bool
{
    return (string) getenv('SMTP_HOST') !== '' && (string) getenv('SMTP_USER') !== '' && (string) getenv('SMTP_PASS') !== '';
}

add_action('phpmailer_init', static function ($m): void {
    if (!schermo_mail_ready()) {
        return;
    }
    $m->isSMTP();
    $m->Host = (string) getenv('SMTP_HOST');
    $m->Port = (int) (getenv('SMTP_PORT') ?: 587);
    $m->SMTPAuth = true;
    $m->Username = (string) getenv('SMTP_USER');
    $m->Password = (string) getenv('SMTP_PASS');
    $m->SMTPSecure = (string) (getenv('SMTP_SECURE') ?: 'tls');
    $from = (string) (getenv('SMTP_FROM') ?: getenv('SMTP_USER'));
    if (is_email($from)) {
        $m->setFrom($from, (string) (getenv('SMTP_FROM_NAME') ?: get_bloginfo('name')), false);
    }
});
add_filter('wp_mail_from', static fn ($f) => schermo_mail_ready() && is_email((string) getenv('SMTP_FROM')) ? (string) getenv('SMTP_FROM') : $f);
add_filter('wp_mail_from_name', static fn ($n) => (string) (getenv('SMTP_FROM_NAME') ?: get_bloginfo('name')));

// Failed sends are remembered so the hub can show them.
add_action('wp_mail_failed', static function (WP_Error $e): void {
    update_option('schermo_mail_error', ['at' => time(), 'error' => $e->get_error_message()], false);
});
add_action('wp_mail_succeeded', static fn () => delete_option('schermo_mail_error'));
