<?php
// One-time store set-up (run by setup.sh; safe to run again): policy page DRAFTS with marked blanks for the client's
// details, a footer "Policies" menu (draft pages stay hidden until published), and WooCommerce email branding.
// Nothing is published here: the owner fills the [BRACKETED] details, has them reviewed, then publishes.

defined('ABSPATH') || exit;

$note = '<!-- wp:paragraph {"className":"draft-note"} --><p class="draft-note"><strong>DRAFT — fill every [BRACKETED] item and have this reviewed before publishing.</strong></p><!-- /wp:paragraph -->';
$p = static fn (string $t): string => "<!-- wp:paragraph --><p>{$t}</p><!-- /wp:paragraph -->";
$h = static fn (string $t): string => "<!-- wp:heading --><h2 class=\"wp-block-heading\">{$t}</h2><!-- /wp:heading -->";
$biz = '[LEGAL BUSINESS NAME]';

$pages = [
    'terms-and-conditions' => ['Terms and Conditions', $note
        . $p("This website is operated by {$biz} (GSTIN [GSTIN]), [REGISTERED ADDRESS] (\"we\", \"us\"). By placing an order you agree to these terms.")
        . $h('Orders and prices') . $p('All prices are in Indian Rupees and include GST unless shown otherwise. Prices shown in other currencies are estimates; you are charged in INR and your bank converts at its own rate. We may cancel an order if an item is unavailable or a price was shown in error, and refund any amount paid in full.')
        . $h('Payment') . $p('Payments are processed securely by [PAYMENT PROVIDER]. We do not store card details.')
        . $h('Delivery') . $p('Delivery is covered by our Shipping Policy. Import duties and taxes for orders outside India are [PAID BY THE CUSTOMER ON DELIVERY / INCLUDED].')
        . $h('Returns') . $p('Returns and refunds are covered by our Returns & Refunds Policy.')
        . $h('Intellectual property') . $p('Names, logos, designs, images and text on this site belong to ' . $biz . ' or its licensors and may not be used without permission.')
        . $h('Governing law') . $p('These terms are governed by the laws of India; courts at [CITY] have jurisdiction.')
        . $h('Contact') . $p('[SUPPORT EMAIL] · [SUPPORT PHONE]')],
    'privacy-policy' => ['Privacy Policy', $note
        . $p("This policy explains how {$biz} collects and uses your personal information when you visit or buy from this website.")
        . $h('What we collect') . $p('Name, email, phone number, billing and delivery address, and order details when you place an order; your country (estimated from your IP address) to show prices in your currency; and basic technical data such as cookies needed for the cart and checkout.')
        . $h('How we use it') . $p('To process and deliver orders, provide customer support, send order updates by email[ and WhatsApp], and meet legal and tax obligations. We do not sell your personal information.')
        . $h('Who we share it with') . $p('Our payment provider ([PAYMENT PROVIDER]), courier partners ([COURIER PARTNERS]) and service providers who host and run this site — only as needed to fulfil your order.')
        . $h('How long we keep it') . $p('Order records are kept for [NUMBER] years for accounting and tax purposes; other data is deleted when no longer needed.')
        . $h('Your choices') . $p('You can ask to see, correct or delete your personal information by writing to [SUPPORT EMAIL].')
        . $h('Contact') . $p("{$biz}, [REGISTERED ADDRESS] · [SUPPORT EMAIL]")],
    'returns-and-refunds' => ['Returns & Refunds', $note
        . $h('Returns') . $p('You can return items within [NUMBER] days of delivery if they are unworn, unwashed and have their original tags. [ITEMS THAT CANNOT BE RETURNED, e.g. socks, caps, sale items].')
        . $h('Exchanges') . $p('[Size exchanges are / are not] available within [NUMBER] days of delivery, subject to stock.')
        . $h('How to return') . $p('Email [SUPPORT EMAIL] with your order number and the items. [We arrange pickup / You send the parcel to our warehouse at ADDRESS]. Return shipping is [FREE / PAID BY THE CUSTOMER].')
        . $h('Refunds') . $p('After the items reach our warehouse and pass inspection, we refund to the original payment method within [NUMBER] working days. Your bank may take a few more days to show it.')
        . $h('Damaged or wrong items') . $p('If an item arrives damaged or is not what you ordered, write to us within [NUMBER] days of delivery with photos and we will replace or refund it at no cost.')],
    'shipping-policy' => ['Shipping Policy', $note
        . $h('Where we ship from') . $p('All orders ship from our warehouse in Delhi, India.')
        . $h('India') . $p('Orders are dispatched within [NUMBER] working days and usually arrive in [NUMBER–NUMBER] working days. Shipping is free on orders above ₹[AMOUNT]; below that it costs ₹[AMOUNT]. [Cash on Delivery is available / not available.]')
        . $h('International') . $p('[We ship to COUNTRIES / We do not ship internationally yet.] Delivery options and estimates are shown at checkout. Import duties and taxes are [paid by the customer on delivery / included].')
        . $h('Tracking') . $p('You receive a tracking link by email[ and WhatsApp] when your order is dispatched.')
        . $h('Questions') . $p('[SUPPORT EMAIL] · [SUPPORT PHONE]')],
    'contact' => ['Contact', $note
        . $p("{$biz}") . $p('Email: [SUPPORT EMAIL]') . $p('Phone / WhatsApp: [SUPPORT PHONE] ([HOURS, e.g. Mon–Sat, 10 am – 6 pm IST])')
        . $p('Address: [REGISTERED ADDRESS]') . $p('GSTIN: [GSTIN]') . $p('Instagram: [INSTAGRAM LINK]')],
];

$ids = [];
foreach ($pages as $slug => [$title, $content]) {
    $existing = get_page_by_path($slug, OBJECT, 'page');
    if ($existing) {
        // Replace WordPress's own sample privacy text, but never a page someone has already worked on or published.
        if ($slug === 'privacy-policy' && $existing->post_status === 'draft' && str_contains($existing->post_content, 'Who we are')) {
            wp_update_post(['ID' => $existing->ID, 'post_content' => $content, 'post_title' => $title]);
        }
        $ids[$slug] = $existing->ID;
        continue;
    }
    $ids[$slug] = wp_insert_post(['post_type' => 'page', 'post_status' => 'draft', 'post_title' => $title, 'post_name' => $slug, 'post_content' => $content]);
}
update_option('wp_page_for_privacy_policy', $ids['privacy-policy']);
// WordPress's default "Sample Page" is removed if nobody changed it.
$sample = get_page_by_path('sample-page', OBJECT, 'page');
if ($sample && str_contains($sample->post_content, 'This is an example page')) {
    wp_trash_post($sample->ID);
}
// WooCommerce's own sample returns page is replaced by ours.
$wc_refund = get_page_by_path('refund_returns', OBJECT, 'page');
if ($wc_refund && $wc_refund->post_status === 'draft') {
    wp_trash_post($wc_refund->ID);
}

// Footer "Policies" menu: drafts are hidden automatically and appear as each page is published.
if (!wp_get_nav_menu_object('Policies')) {
    $menu = wp_create_nav_menu('Policies');
    foreach (['shipping-policy', 'returns-and-refunds', 'terms-and-conditions', 'privacy-policy', 'contact'] as $slug) {
        wp_update_nav_menu_item($menu, 0, ['menu-item-object' => 'page', 'menu-item-object-id' => $ids[$slug], 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish']);
    }
    set_theme_mod('nav_menu_locations', array_merge((array) get_theme_mod('nav_menu_locations', []), ['footer-legal' => $menu]));
}

// Branded WooCommerce emails (only set once, so later changes in WooCommerce → Settings → Emails are kept).
if (get_option('schermo_email_branding') !== '1') {
    $logo = get_stylesheet_directory_uri() . '/assets/img/email-logo.png';
    update_option('woocommerce_email_header_image', $logo);
    update_option('woocommerce_email_base_color', '#0a0a0a');
    update_option('woocommerce_email_background_color', '#f2f2f0');
    update_option('woocommerce_email_body_background_color', '#ffffff');
    update_option('woocommerce_email_text_color', '#2e2e2e');
    update_option('woocommerce_email_from_name', get_bloginfo('name'));
    update_option('woocommerce_email_footer_text', get_bloginfo('name') . ' · Shipped from Delhi, India' . "\n" . 'Store managed by Thoda Logic');
    update_option('schermo_email_branding', '1');
}
echo "store pages: " . count($ids) . " policy drafts ready; email branding set\n";
