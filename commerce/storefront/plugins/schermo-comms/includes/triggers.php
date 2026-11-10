<?php
/**
 * Triggers from real WooCommerce order statuses. This store's flow (TL Commerce Hub): pending/on-hold → processing
 * (paid) → tl-packed → tl-dispatched (courier + AWB entered) → completed ("Mark as delivered"). "Out for delivery" is
 * not tracked by any integration, so no message is sent for it. Each message's key includes the order and the
 * event, so a status that is set twice, a replayed webhook or a retry never sends twice.
 */

defined('ABSPATH') || exit;

/** Real values only, from the order. No payment details. */
function scm_order_vars(WC_Order $o): array
{
    $t = function_exists('tli_tracking') ? tli_tracking($o) : ['courier' => '', 'awb' => '', 'url' => ''];
    return [
        'first_name' => $o->get_billing_first_name(),
        'order_number' => '#' . $o->get_order_number(),
        'order_total' => wp_strip_all_tags(html_entity_decode(wc_price($o->get_total(), ['currency' => $o->get_currency()]))),
        'order_link' => $o->get_view_order_url(),
        'pay_link' => $o->needs_payment() ? $o->get_checkout_payment_url() : $o->get_view_order_url(),
        'courier' => $t['courier'],
        'tracking_number' => $t['awb'],
        'tracking_link' => $t['url'] ?: $o->get_view_order_url(),
    ];
}

/** Queue an order event on each channel this plugin sends it on, to the order's own contact details. */
function scm_order_event(string $event, WC_Order $o, array $extra = []): void
{
    $def = scm_events()[$event] ?? null;
    if (!$def) {
        return;
    }
    $vars = scm_order_vars($o) + $extra;
    foreach ($def['channels'] as $ch) {
        $to = $ch === 'email' ? (string) $o->get_billing_email() : (string) $o->get_billing_phone();
        if ($to !== '') {
            scm_queue($event, $ch, $def['purpose'], $to, $vars, "{$event}:o{$o->get_id()}:{$ch}" . ($extra['_key'] ?? ''), $o->get_id());
        }
    }
}

add_action('woocommerce_order_status_changed', static function (int $id, string $from, string $to, $order): void {
    if (!$order instanceof WC_Order) {
        return;
    }
    $map = [
        'on-hold' => 'order_received',
        'processing' => 'order_confirmed',
        'failed' => 'payment_failed',
        'tl-packed' => 'order_packed',
        'tl-dispatched' => 'order_shipped',
        'completed' => 'order_delivered',
        'cancelled' => 'order_cancelled',
    ];
    if (isset($map[$to])) {
        scm_order_event($map[$to], $order);
    }
    if ($to === 'completed') {
        scm_review_invites_for_order($order); // reviews.php
    }
}, 20, 4);

// Refunds (full or partial): one message per refund.
add_action('woocommerce_order_refunded', static function (int $order_id, int $refund_id): void {
    $o = wc_get_order($order_id);
    $r = wc_get_order($refund_id);
    if ($o && $r) {
        scm_order_event('refund_issued', $o, ['refund_amount' => wp_strip_all_tags(html_entity_decode(wc_price(abs((float) $r->get_amount()), ['currency' => $o->get_currency()]))), '_key' => ":r{$refund_id}"]);
    }
}, 20, 2);

// ---- Marketing: welcome after a new opt-in (one per contact and channel, ever).
add_action('scm_marketing_opt_in', static function (string $channel, string $contact): void {
    scm_queue('welcome', $channel, 'marketing', $contact, ['shop_link' => wc_get_page_permalink('shop')], "welcome:{$channel}:" . md5($contact));
}, 10, 2);

// ---- Marketing: bag reminder for signed-in customers who opted in (we only know who a guest is once they order).
add_action('woocommerce_cart_updated', static function (): void {
    $uid = get_current_user_id();
    if ($uid && WC()->cart && !WC()->cart->is_empty()) {
        update_user_meta($uid, '_scm_cart_touched', time());
    }
});
add_action('init', static function (): void {
    if (function_exists('as_has_scheduled_action') && !as_has_scheduled_action('scm_cart_reminders')) {
        as_schedule_recurring_action(time() + 600, HOUR_IN_SECONDS, 'scm_cart_reminders', [], 'scm');
    }
});
add_action('scm_cart_reminders', static function (): void {
    $hours = max(1, (int) scm_settings()['cart_reminder_hours']);
    $users = get_users(['meta_key' => '_scm_cart_touched', 'meta_compare' => '<', 'meta_value' => time() - $hours * HOUR_IN_SECONDS, 'number' => 200, 'fields' => ['ID']]);
    foreach ($users as $u) {
        $uid = (int) $u->ID;
        $touched = (int) get_user_meta($uid, '_scm_cart_touched', true);
        $cart = get_user_meta($uid, '_woocommerce_persistent_cart_' . get_current_blog_id(), true);
        $items = is_array($cart) ? ($cart['cart'] ?? []) : [];
        delete_user_meta($uid, '_scm_cart_touched'); // one reminder per abandoned bag
        if (!$items) {
            continue;
        }
        // Skip if they ordered after last touching the bag.
        $recent = wc_get_orders(['customer_id' => $uid, 'limit' => 1, 'date_created' => '>' . $touched, 'return' => 'ids']);
        if ($recent) {
            continue;
        }
        $names = [];
        foreach ($items as $it) {
            $p = wc_get_product((int) ($it['variation_id'] ?: $it['product_id']));
            if ($p) {
                $names[] = '• ' . $p->get_name();
            }
        }
        $user = get_userdata($uid);
        $vars = ['first_name' => (string) get_user_meta($uid, 'billing_first_name', true), 'cart_items' => implode("\n", $names), 'cart_link' => wc_get_cart_url()];
        scm_queue('cart_reminder', 'email', 'marketing', (string) $user->user_email, $vars, "cart:u{$uid}:t{$touched}:email");
        $phone = (string) get_user_meta($uid, 'billing_phone', true);
        if ($phone !== '') {
            scm_queue('cart_reminder', 'whatsapp', 'marketing', $phone, $vars, "cart:u{$uid}:t{$touched}:wa");
        }
    }
});

/** A drop/collection announcement to everyone opted in on the chosen channels (queued; dry-run logs only). */
function scm_send_drop(string $headline, string $message, string $link, array $channels): int
{
    $n = 0;
    $key = md5($headline . $message . $link);
    foreach ($channels as $ch) {
        foreach (scm_marketing_audience($ch) as $contact) {
            $n += scm_queue('drop', $ch, 'marketing', $contact, ['headline' => $headline, 'message' => $message, 'link' => $link], "drop:{$key}:{$ch}:" . md5($contact)) ? 1 : 0;
        }
    }
    return $n;
}
