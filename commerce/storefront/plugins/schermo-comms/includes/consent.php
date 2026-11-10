<?php
/**
 * Consent records. Each (contact, channel, purpose) has one row: channel = email|whatsapp, purpose = updates|marketing.
 * Order updates and marketing are separate; agreeing to one never implies the other. Withdrawals are kept (with when
 * and how), never deleted, so the history stays traceable.
 *
 * Checkout asks with optional, unchecked boxes (WooCommerce's block-checkout "additional fields" API). Declining
 * never blocks checkout. The exact wording shown is stored with a version.
 */

defined('ABSPATH') || exit;

/** The consent wording shown to customers. Change the text → bump the version, so records show what was agreed to. */
function scm_wordings(): array
{
    return [
        'whatsapp:updates' => ['v1', __('Send me order updates on WhatsApp (to my phone number above).', 'schermo-comms')],
        'email:marketing' => ['v1', __('Email me about new drops and offers from Schermo. I can unsubscribe at any time.', 'schermo-comms')],
        'whatsapp:marketing' => ['v1', __('Send me new drops and offers on WhatsApp. I can reply STOP at any time.', 'schermo-comms')],
    ];
}

/** Normalised contact: lower-case email, or a phone number in E.164-like digits (Indian numbers get +91). */
function scm_contact(string $channel, string $value): string
{
    if ($channel === 'email') {
        return strtolower(trim($value));
    }
    $d = preg_replace('/\D+/', '', $value);
    if (strlen($d) === 10) {
        $d = '91' . $d; // a 10-digit number in this India-based store
    }
    return $d === '' ? '' : '+' . ltrim($d, '0');
}

function scm_consent_set(string $channel, string $purpose, string $contact, bool $granted, string $source, array $extra = []): void
{
    global $wpdb;
    $contact = scm_contact($channel, $contact);
    if ($contact === '') {
        return;
    }
    $t = $wpdb->prefix . 'scm_consents';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE contact = %s AND channel = %s AND purpose = %s", $contact, $channel, $purpose), ARRAY_A); // phpcs:ignore
    [$ver, $text] = scm_wordings()["{$channel}:{$purpose}"] ?? ['', ''];
    $now = scm_now();
    $data = $granted
        ? ['status' => 'granted', 'source' => $source, 'wording_version' => $ver, 'wording' => $text, 'given_at' => $now, 'withdrawn_at' => null, 'withdrawn_via' => '']
        : ['status' => 'withdrawn', 'withdrawn_at' => $now, 'withdrawn_via' => $source];
    $data += ['user_id' => (int) ($extra['user_id'] ?? 0), 'order_id' => (int) ($extra['order_id'] ?? 0), 'updated_at' => $now];
    if ($row) {
        if (!$granted && $row['status'] !== 'granted') {
            return; // nothing to withdraw
        }
        $wpdb->update($t, $data, ['id' => $row['id']]);
    } elseif ($granted) {
        $wpdb->insert($t, $data + ['contact' => $contact, 'channel' => $channel, 'purpose' => $purpose]);
    }
    if ($granted && $purpose === 'marketing' && (!$row || $row['status'] !== 'granted')) {
        do_action('scm_marketing_opt_in', $channel, $contact, $extra);
    }
}

function scm_consent_ok(string $channel, string $purpose, string $contact): bool
{
    global $wpdb;
    $contact = scm_contact($channel, $contact);
    return $contact !== '' && 'granted' === $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}scm_consents WHERE contact = %s AND channel = %s AND purpose = %s", $contact, $channel, $purpose)); // phpcs:ignore
}

/** Contacts with granted marketing consent on a channel. */
function scm_marketing_audience(string $channel): array
{
    global $wpdb;
    return $wpdb->get_col($wpdb->prepare("SELECT contact FROM {$wpdb->prefix}scm_consents WHERE channel = %s AND purpose = 'marketing' AND status = 'granted'", $channel)); // phpcs:ignore
}

// ---- Checkout boxes (block checkout). Optional and unchecked; values are saved on the order and recorded below.
add_action('woocommerce_init', static function (): void {
    // The owner chose a minimal checkout (only the terms checkbox), so these boxes are off unless switched on with
    // add_filter('scm_checkout_consent_fields', '__return_true'). Without them no WhatsApp or marketing consent is
    // collected at checkout, so those messages are not sent to checkout customers.
    if (!function_exists('woocommerce_register_additional_checkout_field') || !apply_filters('scm_checkout_consent_fields', false)) {
        return;
    }
    foreach (['whatsapp:updates' => 'scm/wa-updates', 'email:marketing' => 'scm/email-marketing', 'whatsapp:marketing' => 'scm/wa-marketing'] as $key => $id) {
        woocommerce_register_additional_checkout_field([
            'id' => $id,
            'label' => scm_wordings()[$key][1],
            'location' => 'order',
            'type' => 'checkbox',
            'required' => false,
        ]);
    }
});

/** Record the checkout choices once the order exists (block checkout and classic checkout). */
function scm_record_checkout_consent(WC_Order $order): void
{
    if ($order->get_meta('_scm_consent_recorded')) {
        return;
    }
    $fields = class_exists(\Automattic\WooCommerce\Blocks\Package::class) && function_exists('wc_get_container')
        ? wc_get_container()->get(\Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class) : null;
    $get = static fn (string $id): bool => $fields ? (bool) $fields->get_field_from_object($id, $order, 'other') : false;
    $extra = ['user_id' => $order->get_customer_id(), 'order_id' => $order->get_id()];
    $phone = (string) $order->get_billing_phone();
    $email = (string) $order->get_billing_email();
    if ($get('scm/wa-updates') && $phone !== '') {
        scm_consent_set('whatsapp', 'updates', $phone, true, 'checkout', $extra);
    }
    if ($get('scm/email-marketing') && $email !== '') {
        scm_consent_set('email', 'marketing', $email, true, 'checkout', $extra);
    }
    if ($get('scm/wa-marketing') && $phone !== '') {
        scm_consent_set('whatsapp', 'marketing', $phone, true, 'checkout', $extra);
    }
    $order->update_meta_data('_scm_consent_recorded', 1);
    $order->save();
}
add_action('woocommerce_store_api_checkout_order_processed', 'scm_record_checkout_consent', 5);
add_action('woocommerce_checkout_order_processed', static fn ($id) => ($o = wc_get_order($id)) ? scm_record_checkout_consent($o) : null, 5);

// ---- Unsubscribe: a signed link in every marketing message (and a one-click POST for email clients, RFC 8058).
function scm_unsubscribe_url(string $channel, string $contact): string
{
    return add_query_arg('scm_unsub', scm_sign(['c' => $channel, 'k' => scm_contact($channel, $contact)]), home_url('/'));
}

add_action('template_redirect', static function (): void {
    $tok = isset($_REQUEST['scm_unsub']) ? sanitize_text_field(wp_unslash($_REQUEST['scm_unsub'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- signed token
    if ($tok === '') {
        return;
    }
    $d = scm_verify($tok);
    $ok = false;
    if ($d && isset($d['c'], $d['k'])) {
        scm_consent_set((string) $d['c'], 'marketing', (string) $d['k'], false, $_SERVER['REQUEST_METHOD'] === 'POST' ? 'one-click' : 'unsubscribe-link');
        $ok = true;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        status_header($ok ? 200 : 400);
        exit;
    }
    wp_die(
        $ok ? esc_html__('You are unsubscribed from Schermo marketing messages on this channel. Order updates are not affected.', 'schermo-comms')
            : esc_html__('This unsubscribe link is not valid or has expired. Reply STOP on WhatsApp, or contact us, and we will remove you.', 'schermo-comms'),
        esc_html__('Unsubscribe', 'schermo-comms'),
        ['response' => $ok ? 200 : 400]
    );
});
