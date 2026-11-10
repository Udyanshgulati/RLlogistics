<?php
/** Order stages for the warehouse: Paid → Packed → Dispatched → Delivered, on top of WooCommerce's own statuses. */

declare(strict_types=1);

defined('ABSPATH') || exit;

const TLI_STAGES = [
    'pending' => ['Awaiting payment', 'neutral'],
    'on-hold' => ['On hold', 'warn'],
    'processing' => ['To pack', 'warn'],
    'tl-packed' => ['Packed', 'info'],
    'tl-dispatched' => ['Dispatched', 'info'],
    'completed' => ['Delivered', 'ok'],
    'cancelled' => ['Cancelled', 'neutral'],
    'refunded' => ['Refunded', 'neutral'],
    'failed' => ['Payment failed', 'bad'],
];
const TLI_NEXT = ['processing' => ['tl-packed', 'Mark as packed'], 'tl-packed' => ['tl-dispatched', 'Mark as dispatched'], 'tl-dispatched' => ['completed', 'Mark as delivered']];

add_action('init', static function (): void {
    register_post_status('wc-tl-packed', ['label' => 'Packed', 'public' => false, 'exclude_from_search' => false, 'show_in_admin_all_list' => true, 'show_in_admin_status_list' => true, 'label_count' => _n_noop('Packed <span class="count">(%s)</span>', 'Packed <span class="count">(%s)</span>')]);
    register_post_status('wc-tl-dispatched', ['label' => 'Dispatched', 'public' => false, 'exclude_from_search' => false, 'show_in_admin_all_list' => true, 'show_in_admin_status_list' => true, 'label_count' => _n_noop('Dispatched <span class="count">(%s)</span>', 'Dispatched <span class="count">(%s)</span>')]);
});
add_filter('wc_order_statuses', static function (array $s): array {
    $out = [];
    foreach ($s as $k => $v) {
        $out[$k] = $v;
        if ($k === 'wc-processing') {
            $out['wc-tl-packed'] = 'Packed';
            $out['wc-tl-dispatched'] = 'Dispatched';
        }
    }
    return $out;
});
// Packed and dispatched orders are already paid: they count in reports and keep their stock reduced.
add_filter('woocommerce_order_is_paid_statuses', static fn (array $s): array => array_merge($s, ['tl-packed', 'tl-dispatched']));
add_filter('woocommerce_reports_order_statuses', static fn ($s) => is_array($s) ? array_merge($s, ['tl-packed', 'tl-dispatched']) : $s);

function tli_stage(WC_Order $o): array
{
    return TLI_STAGES[$o->get_status()] ?? [wc_get_order_status_name($o->get_status()), 'neutral'];
}

add_action('admin_post_tli_next_stage', static function (): void {
    if (!current_user_can('tl_fulfil')) {
        wp_die('Not allowed.', 403);
    }
    $id = absint($_POST['order_id'] ?? 0);
    check_admin_referer('tli_next_' . $id);
    $o = wc_get_order($id);
    if ($o && isset(TLI_NEXT[$o->get_status()])) {
        [$to] = TLI_NEXT[$o->get_status()];
        if ($to === 'tl-dispatched') {
            $t = tli_tracking_from_post();
            if (!$t) {
                wp_safe_redirect(add_query_arg(['tli_msg' => rawurlencode('Enter the courier and AWB number to mark the order as dispatched.'), 'tli_ok' => '0'], wp_get_referer() ?: admin_url('admin.php?page=tl-orders')));
                exit;
            }
            tli_set_tracking($o, $t);
            $o->add_order_note(sprintf('Shipped with %s, AWB %s.', $t['courier'], $t['awb']));
        }
        $o->update_status($to, sprintf('TL Commerce Hub: %s by %s.', TLI_STAGES[$to][0], wp_get_current_user()->display_name));
    }
    wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=tl-orders'));
    exit;
});
