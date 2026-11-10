<?php
// Runs on every container start (docker/setup.sh). Idempotent; never overwrites what the owner set in WP admin.

defined('ABSPATH') || exit;

// Razorpay: installed and active, but switched off until the owner pastes keys in
// WooCommerce → Settings → Payments → Razorpay and ticks "Enable". Keys live only in WP admin.
if (get_option('woocommerce_razorpay_settings') === false) {
    add_option('woocommerce_razorpay_settings', [
        'enabled' => 'no',
        'title' => 'Pay online — UPI, cards, netbanking, wallets',
        'description' => 'Pay securely with Razorpay.',
        'key_id' => '',
        'key_secret' => '',
        'payment_action' => 'capture',
        'order_success_message' => 'Thank you for your order. Your payment was successful.',
    ]);
    echo "store: Razorpay installed, disabled until keys are entered\n";
}

// Shipping: an India zone. Its rates come from the client sheet (import-sheet.php) or WP admin.
// Everything outside India falls into WooCommerce's built-in "Rest of the world" zone.
$names = array_column(WC_Shipping_Zones::get_zones(), 'zone_name');
if (!in_array('India', $names, true)) {
    $zone = new WC_Shipping_Zone();
    $zone->set_zone_name('India');
    $zone->set_zone_order(0);
    $zone->add_location('IN', 'country');
    $zone->save();
    echo "store: India shipping zone created (no rates yet)\n";
}

// Checkout content (v1, once): no order-note box, and a required "I agree to the Terms and Conditions" checkbox
// linked to the published Terms page (the owner's choice for a minimal checkout).
if ((int) get_option('schermo_checkout_v') < 1) {
    $page = get_post(wc_get_page_id('checkout'));
    if ($page) {
        $c = $page->post_content;
        $c = preg_replace('#\s*<!-- wp:woocommerce/checkout-order-note-block[^>]*-->.*?<!-- /wp:woocommerce/checkout-order-note-block -->#s', '', $c);
        $c = preg_replace('#<!-- wp:woocommerce/checkout-terms-block[^>]*-->#', '<!-- wp:woocommerce/checkout-terms-block {"checkbox":true} -->', $c);
        wp_update_post(['ID' => $page->ID, 'post_content' => $c]);
    }
    $terms = get_page_by_path('terms-and-conditions', OBJECT, 'page');
    if ($terms && $terms->post_status === 'publish') {
        update_option('woocommerce_terms_page_id', $terms->ID);
    }
    update_option('schermo_checkout_v', 1, false);
    echo "store: checkout simplified (terms checkbox, no order note)\n";
}
