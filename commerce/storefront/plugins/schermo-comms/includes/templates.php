<?php
/**
 * Message definitions. Each event says its purpose (updates = transactional, marketing) and which channels this plugin
 * sends on. Where WooCommerce or the TL Commerce Hub already email the customer (processing, completed, refunded,
 * dispatched), this plugin does not send a second email — only WhatsApp, for customers who opted in.
 *
 * Email subject/body are editable in WooCommerce → Schermo messages. {placeholders} are filled only with real order,
 * product, tracking and link values. WhatsApp messages use the owner's approved template (name + language per event);
 * "wa" lists the body parameters, in order, that the approved template must have.
 */

defined('ABSPATH') || exit;

function scm_events(): array
{
    return [
        'order_received' => ['label' => 'Order received (awaiting payment)', 'purpose' => 'updates', 'channels' => ['whatsapp'], 'wa' => ['first_name', 'order_number']],
        'order_confirmed' => ['label' => 'Order confirmed (payment received)', 'purpose' => 'updates', 'channels' => ['whatsapp'], 'wa' => ['first_name', 'order_number', 'order_total']],
        'payment_failed' => ['label' => 'Payment failed', 'purpose' => 'updates', 'channels' => ['email', 'whatsapp'], 'wa' => ['first_name', 'order_number', 'pay_link'],
            'subject' => 'Payment for order {order_number} did not go through', 'body' => "Hi {first_name},\n\nThe payment for your Schermo order {order_number} did not go through, so the order is not confirmed yet. You can try again here: {pay_link}\n\nNo money was taken for this attempt. If it was, your bank will return it automatically."],
        'order_packed' => ['label' => 'Order packed', 'purpose' => 'updates', 'channels' => ['email', 'whatsapp'], 'wa' => ['first_name', 'order_number'],
            'subject' => 'Your Schermo order {order_number} is packed', 'body' => "Hi {first_name},\n\nYour order {order_number} is packed at our warehouse in Delhi. We will send the tracking details as soon as it is handed to the courier.\n\nView your order: {order_link}"],
        'order_shipped' => ['label' => 'Order dispatched (with tracking)', 'purpose' => 'updates', 'channels' => ['whatsapp'], 'wa' => ['first_name', 'order_number', 'courier', 'tracking_number', 'tracking_link']],
        'order_delivered' => ['label' => 'Order delivered', 'purpose' => 'updates', 'channels' => ['whatsapp'], 'wa' => ['first_name', 'order_number']],
        'order_cancelled' => ['label' => 'Order cancelled', 'purpose' => 'updates', 'channels' => ['email', 'whatsapp'], 'wa' => ['first_name', 'order_number'],
            'subject' => 'Your Schermo order {order_number} is cancelled', 'body' => "Hi {first_name},\n\nYour order {order_number} has been cancelled. If you paid for it, the refund is being processed to your original payment method.\n\nQuestions? Reply to this email."],
        'refund_issued' => ['label' => 'Refund issued', 'purpose' => 'updates', 'channels' => ['whatsapp'], 'wa' => ['first_name', 'order_number', 'refund_amount']],
        'review_request' => ['label' => 'Review request (after delivery)', 'purpose' => 'updates', 'channels' => ['email', 'whatsapp'], 'wa' => ['first_name', 'product_name', 'review_link'],
            'subject' => 'How is your {product_list_short}?', 'body' => "Hi {first_name},\n\nYour Schermo order {order_number} was delivered. If you have a moment, tell other shoppers what you think — each link below is for one piece from your order:\n\n{review_links}\n\nThe links are only for you and work once per piece. Reviews are optional."],
        'welcome' => ['label' => 'Welcome (after marketing opt-in)', 'purpose' => 'marketing', 'channels' => ['email', 'whatsapp'], 'wa' => [],
            'subject' => 'Welcome to Schermo', 'body' => "Thanks for joining. We will write when there is a new drop or something worth knowing — not more.\n\n{coupon_line}\n\nShop the collections: {shop_link}"],
        'cart_reminder' => ['label' => 'Bag reminder (signed-in customers)', 'purpose' => 'marketing', 'channels' => ['email', 'whatsapp'], 'wa' => ['first_name', 'cart_link'],
            'subject' => 'Your Schermo bag is waiting', 'body' => "Hi {first_name},\n\nYou left these in your bag:\n\n{cart_items}\n\nPick up where you left off: {cart_link}"],
        'drop' => ['label' => 'Drop / collection announcement', 'purpose' => 'marketing', 'channels' => ['email', 'whatsapp'], 'wa' => ['headline', 'link'],
            'subject' => '{headline}', 'body' => "{message}\n\n{link}"],
    ];
}

/** Template id + version (a short hash of the editable text, so the log shows exactly which wording went out). */
function scm_template(string $event, string $channel): array
{
    $s = scm_settings();
    if ($channel === 'whatsapp') {
        $wa = $s['wa_templates'][$event] ?? [];
        return ['id' => (string) ($wa['name'] ?? ''), 'version' => (string) ($wa['lang'] ?? '')];
    }
    [$subj, $body] = scm_email_text($event);
    return ['id' => $event, 'version' => substr(md5($subj . $body), 0, 8)];
}

function scm_email_text(string $event): array
{
    $e = scm_events()[$event] ?? [];
    $o = get_option('scm_tpl', []);
    return [(string) ($o[$event]['subject'] ?? $e['subject'] ?? ''), (string) ($o[$event]['body'] ?? $e['body'] ?? '')];
}

function scm_fill(string $text, array $vars): string
{
    return (string) preg_replace_callback('/\{([a-z_]+)\}/', static fn ($m) => array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : '', $text);
}

/** Render for a channel. Email: subject + branded HTML; WhatsApp: template name/lang + ordered parameters. */
function scm_render(string $event, string $channel, array $vars): array
{
    $def = scm_events()[$event] ?? [];
    if ($channel === 'whatsapp') {
        $wa = scm_settings()['wa_templates'][$event] ?? [];
        $params = array_map(static fn ($k) => (string) ($vars[$k] ?? ''), $def['wa'] ?? []);
        return ['wa_template' => (string) ($wa['name'] ?? ''), 'wa_lang' => (string) ($wa['lang'] ?? 'en'), 'wa_params' => $params,
            'preview' => 'WhatsApp template "' . ($wa['name'] ?? '(none)') . '" with ' . implode(' | ', $params)];
    }
    [$subj, $body] = scm_email_text($event);
    $subject = scm_fill($subj, $vars);
    $text = scm_fill($body, $vars);
    $html = wpautop(make_clickable(esc_html($text)));
    if ($def['purpose'] === 'marketing') {
        $html .= '<p style="font-size:12px;color:#777">' . sprintf('You are receiving this because you opted in to Schermo emails. <a href="%s">Unsubscribe</a>', esc_url(scm_unsubscribe_url('email', (string) ($vars['_to'] ?? '')))) . '</p>';
    }
    if (function_exists('WC') && WC()->mailer()) {
        $html = WC()->mailer()->wrap_message($subject, $html);
    }
    return ['subject' => $subject, 'html' => $html, 'preview' => $subject . ' — ' . $text];
}
