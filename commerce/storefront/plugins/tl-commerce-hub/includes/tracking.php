<?php
/**
 * Shipment tracking. When an order is marked as dispatched the team enters the courier and the AWB (tracking) number.
 * The customer gets an "Order dispatched" email with the tracking details, sees them on their account's order page,
 * and can look the order up on the public "Track your order" page. For orders from other stores the hub sends the
 * tracking details to that store, which emails its own customer.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

const TLI_COURIERS_DEFAULT = "Delhivery\nBlue Dart\nDTDC\nXpressbees\nEkart\nIndia Post\nShiprocket\nOther";

/** Courier name => tracking link template ("" when no link is set). Settings lines: "Name | https://…{awb}". */
function tli_couriers(): array
{
    $out = [];
    foreach (preg_split('/\R/', (string) get_option('tli_couriers', TLI_COURIERS_DEFAULT)) as $line) {
        [$name, $url] = array_map('trim', array_pad(explode('|', $line, 2), 2, ''));
        if ($name !== '') {
            $out[$name] = str_contains($url, '{awb}') && wp_http_validate_url(str_replace('{awb}', 'x', $url)) ? $url : '';
        }
    }
    return $out ?: ['Other' => ''];
}

function tli_track_url(string $courier, string $awb, string $custom = ''): string
{
    if ($custom !== '' && wp_http_validate_url($custom)) {
        return $custom;
    }
    $tpl = tli_couriers()[$courier] ?? '';
    return $tpl !== '' && $awb !== '' ? str_replace('{awb}', rawurlencode($awb), $tpl) : '';
}

/** Reads and validates the courier fields of a dispatch form. Returns null when the AWB is missing. */
function tli_tracking_from_post(): ?array
{
    $courier = sanitize_text_field(wp_unslash($_POST['courier'] ?? ''));
    $awb = preg_replace('/[^A-Za-z0-9\-\/ ]/', '', sanitize_text_field(wp_unslash($_POST['awb'] ?? '')));
    $link = esc_url_raw(wp_unslash($_POST['track_link'] ?? ''));
    if ($awb === '' || $courier === '') {
        return null;
    }
    return ['courier' => mb_substr($courier, 0, 60), 'awb' => mb_substr(trim($awb), 0, 60), 'url' => tli_track_url($courier, $awb, $link)];
}

function tli_set_tracking(WC_Order $o, array $t): void
{
    $o->update_meta_data('_tl_courier', $t['courier']);
    $o->update_meta_data('_tl_awb', $t['awb']);
    $o->update_meta_data('_tl_track_url', $t['url']);
    $o->save_meta_data();
}

function tli_tracking(WC_Order $o): array
{
    return ['courier' => (string) $o->get_meta('_tl_courier'), 'awb' => (string) $o->get_meta('_tl_awb'), 'url' => (string) $o->get_meta('_tl_track_url')];
}

/** Courier + AWB inputs used in the "Mark as dispatched" forms. */
function tli_dispatch_fields(): string
{
    $opts = '';
    foreach (array_keys(tli_couriers()) as $c) {
        $opts .= sprintf('<option>%s</option>', esc_html($c));
    }
    return '<span class="tli-dispatch"><select name="courier" class="tli-input sm" required aria-label="Courier">' . $opts . '</select>'
        . '<input class="tli-input sm" name="awb" required maxlength="60" placeholder="AWB / tracking no." aria-label="AWB number">'
        . '<input class="tli-input sm" type="url" name="track_link" placeholder="Tracking link (optional)" aria-label="Tracking link"></span>';
}

/** Small courier · AWB line for admin tables. */
function tli_tracking_line(string $courier, string $awb, string $url): string
{
    if ($awb === '') {
        return '';
    }
    $txt = esc_html(trim($courier . ' · ' . $awb, ' ·'));
    return '<span class="tli-awb">' . ($url ? '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . $txt . '</a>' : $txt) . '</span>';
}

// Time of every stage, for the customer's timeline.
add_action('woocommerce_order_status_changed', static function ($id, $from, $to, $order): void {
    if ($order instanceof WC_Order && in_array($to, ['processing', 'tl-packed', 'tl-dispatched', 'completed'], true) && !$order->get_meta('_tl_at_' . $to)) {
        $order->update_meta_data('_tl_at_' . $to, time());
        $order->save_meta_data();
    }
}, 5, 4);

/* ── Customer email ───────────────────────────────────────────────────── */
add_filter('woocommerce_email_actions', static fn (array $a): array => array_merge($a, ['woocommerce_order_status_tl-dispatched']));
add_filter('woocommerce_email_classes', static function (array $emails): array {
    require_once TLI_DIR . '/includes/class-tli-email-dispatched.php';
    $emails['TLI_Email_Dispatched'] = new TLI_Email_Dispatched();
    return $emails;
});

/** Tracking block for emails and the account page. */
function tli_tracking_html(WC_Order $o, bool $email = false): string
{
    $t = tli_tracking($o);
    if ($t['awb'] === '') {
        return '';
    }
    $btn = !$t['url'] ? '' : ($email ? sprintf('<p style="margin:12px 0 0"><a href="%s" style="display:inline-block;background:#0a0a0a;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;font-weight:600">Track your parcel</a></p>', esc_url($t['url']))
        : sprintf('<p style="margin:12px 0 0"><a class="button" href="%s" target="_blank" rel="noopener">Track your parcel</a></p>', esc_url($t['url'])));
    $page = (int) get_option('tli_track_page');
    $help = !$t['url'] && $page ? sprintf('<p style="margin:8px 0 0">You can also check the status on our <a href="%s">Track your order</a> page.</p>', esc_url(get_permalink($page))) : '';
    $style = $email ? 'border:1px solid #e4e2dc;border-radius:8px;padding:16px;margin:0 0 24px' : '';
    return sprintf('<section class="tl-tracking" style="%s"><h2 style="margin:0 0 8px">Shipment</h2><p style="margin:0">Courier: <strong>%s</strong><br>Tracking number (AWB): <strong>%s</strong></p>%s%s</section>',
        esc_attr($style), esc_html($t['courier']), esc_html($t['awb']), $btn, $help);
}

add_action('woocommerce_order_details_before_order_table', static function ($o): void {
    if ($o instanceof WC_Order) {
        echo tli_tracking_html($o); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the builder
        echo tli_customer_timeline($o); // phpcs:ignore
    }
});

function tli_customer_timeline(WC_Order $o): string
{
    $steps = ['processing' => 'Order confirmed', 'tl-packed' => 'Packed at our Delhi warehouse', 'tl-dispatched' => 'Handed to the courier', 'completed' => 'Delivered'];
    $status = $o->get_status();
    if (!isset($steps[$status])) {
        return '';
    }
    $reached = array_search($status, array_keys($steps), true);
    $html = '<ol class="tl-timeline">';
    $i = 0;
    foreach ($steps as $k => $label) {
        $at = (int) $o->get_meta('_tl_at_' . $k);
        if (!$at && $k === 'processing' && $o->get_date_paid()) {
            $at = $o->get_date_paid()->getTimestamp();
        }
        $html .= sprintf('<li class="%s"><span class="dot"></span><b>%s</b><small>%s</small></li>', $i <= $reached ? 'done' : '', esc_html($label), $i <= $reached && $at ? esc_html(wp_date('d M Y', $at)) : '');
        $i++;
    }
    return $html . '</ol>';
}

/* ── Public "Track your order" page: [tl_track_order] ─────────────────── */
add_shortcode('tl_track_order', static function (): string {
    $num = sanitize_text_field(wp_unslash($_GET['order'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification -- read-only lookup, rate limited
    $email = sanitize_email(wp_unslash($_GET['email'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification
    $result = '';
    if ($num !== '' || $email !== '') {
        $ip = md5((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $tries = (int) get_transient('tli_track_' . $ip);
        set_transient('tli_track_' . $ip, $tries + 1, 15 * MINUTE_IN_SECONDS);
        $o = $tries < 15 ? wc_get_order(absint(ltrim($num, '#'))) : null;
        if ($tries >= 15) {
            $result = '<p class="tl-track-msg">Too many attempts. Please try again in 15 minutes.</p>';
        } elseif (!$o instanceof WC_Order || $o->get_type() !== 'shop_order' || $email === '' || strcasecmp($o->get_billing_email(), $email) !== 0) {
            $result = '<p class="tl-track-msg">We could not find an order with that number and email. Check both and try again.</p>';
        } else {
            $stage = TLI_STAGES[$o->get_status()][0] ?? wc_get_order_status_name($o->get_status());
            $items = implode(', ', array_map(static fn ($i) => $i->get_quantity() . '× ' . $i->get_name(), array_values($o->get_items())));
            $result = sprintf('<div class="tl-track-result"><p class="tl-track-head">Order <b>#%s</b> · placed %s<br><span class="tl-track-stage">%s</span></p><p class="tl-track-items">%s</p>%s%s</div>',
                esc_html($o->get_order_number()), esc_html(wc_format_datetime($o->get_date_created())), esc_html($stage), esc_html($items), tli_customer_timeline($o), tli_tracking_html($o));
        }
    }
    return sprintf('<form class="tl-track" method="get"><p>Enter your order number and the email you used at checkout.</p><label>Order number<input name="order" required value="%s" placeholder="e.g. 1042" inputmode="numeric"></label><label>Email<input type="email" name="email" required value="%s" placeholder="you@example.com"></label><button class="button">Track order</button></form>%s',
        esc_attr($num), esc_attr($email), $result);
});

/** Creates the public tracking page once and adds it to the footer "Policies" menu. */
function tli_install_tracking(): void
{
    $page = get_page_by_path('track-order', OBJECT, 'page');
    if (!$page) {
        $id = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Track your order', 'post_name' => 'track-order', 'post_content' => '<!-- wp:shortcode -->[tl_track_order]<!-- /wp:shortcode -->']);
    } else {
        $id = $page->ID;
    }
    if (!$id || is_wp_error($id)) {
        return;
    }
    update_option('tli_track_page', (int) $id);
    $menu = wp_get_nav_menu_object('Policies');
    if ($menu && !get_option('tli_track_in_menu')) {
        wp_update_nav_menu_item($menu->term_id, 0, ['menu-item-object' => 'page', 'menu-item-object-id' => (int) $id, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish', 'menu-item-position' => 0]);
        update_option('tli_track_in_menu', 1);
    }
}

add_action('wp_head', static function (): void {
    if (is_page((int) get_option('tli_track_page')) || is_account_page()) {
        echo '<style>.tl-track{display:grid;gap:12px;max-width:440px;margin:0 0 24px}.tl-track label{display:grid;gap:6px;font-weight:600}.tl-track input{padding:12px;border:1px solid rgba(127,127,127,.4);border-radius:8px;background:transparent;color:inherit}.tl-track-msg{padding:12px 14px;border-radius:8px;background:rgba(220,38,38,.12)}.tl-track-result,.tl-tracking{padding:18px;border:1px solid rgba(127,127,127,.3);border-radius:12px;margin:0 0 24px}.tl-track-stage{display:inline-block;margin-top:6px;font-weight:700}.tl-timeline{list-style:none;margin:16px 0;padding:0;display:grid;grid-template-columns:repeat(4,1fr);gap:8px}.tl-timeline li{display:grid;gap:4px;opacity:.45;font-size:13px}.tl-timeline li.done{opacity:1}.tl-timeline .dot{width:12px;height:12px;border-radius:50%;border:2px solid currentColor}.tl-timeline li.done .dot{background:currentColor}@media(max-width:600px){.tl-timeline{grid-template-columns:1fr 1fr}}</style>';
    }
});
