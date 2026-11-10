<?php
// Store pages (run by setup.sh on every deploy; safe to run again): Terms, Privacy, Refunds & Cancellations, Shipping,
// Contact and FAQ, published, plus the footer "Policies" menu, store-wide rules (prepaid only, worldwide delivery,
// no returns) and WooCommerce email branding.
//
// Business details (legal name, GSTIN, address, support contact, grievance officer, delivery times, courts) are not
// written into the text: they come from TL Commerce Hub → Settings through the [tl_store] shortcodes, and a detail
// that is not filled in is simply left out. A page is regenerated only while nobody has edited it (its content still
// matches what this script wrote last time), so edits made in WordPress are never overwritten.

defined('ABSPATH') || exit;

$p = static fn (string $t): string => "<!-- wp:paragraph -->\n<p>{$t}</p>\n<!-- /wp:paragraph -->\n";
$h = static fn (string $t): string => "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">{$t}</h2>\n<!-- /wp:heading -->\n";
$ul = static fn (array $items): string => "<!-- wp:list -->\n<ul class=\"wp-block-list\">" . implode('', array_map(static fn ($i) => "<!-- wp:list-item --><li>{$i}</li><!-- /wp:list-item -->", $items)) . "</ul>\n<!-- /wp:list -->\n";
$sc = static fn (string $s): string => "<!-- wp:shortcode -->\n{$s}\n<!-- /wp:shortcode -->\n";
$link = static fn (string $slug, string $text): string => '<a href="' . esc_url(home_url('/' . $slug . '/')) . '">' . $text . '</a>';
$updated = 'Last updated: ' . wp_date('j F Y');
$dispatch = '[tl_store field="dispatch" fallback="a few working days"]';

$pages = [
    'terms-and-conditions' => ['Terms and Conditions',
        $sc('[tl_store_block type="operator"]')
        . $p('By browsing this website or placing an order you agree to these terms. Please read them together with our ' . $link('shipping-policy', 'Shipping Policy') . ', ' . $link('returns-and-refunds', 'Refunds &amp; Cancellations') . ' and ' . $link('privacy-policy', 'Privacy Policy') . '.')
        . $h('Products')
        . $p('We describe and photograph every product as accurately as we can. Colours can look slightly different on different screens, and sizes follow the size guide on each product page — please check it before you order, because orders cannot be returned or exchanged for size.')
        . $h('Prices and payment')
        . $ul(['All prices are set in Indian Rupees (INR) and include GST. Prices shown in other currencies are estimates for your convenience; you are charged in INR and your bank converts at its own rate.',
            'Payment is taken online at checkout through our payment partner, using the methods shown there. We never see or store your full card details.',
            'All orders are prepaid. We do not offer Cash on Delivery.',
            'Shipping charges, if any, are shown at checkout before you pay. Import duties and taxes charged by your country are not included (see the Shipping Policy).',
            'If a product is unavailable or was listed at an obviously wrong price, we may cancel the order; any amount paid is refunded in full to the original payment method.'])
        . $h('Orders')
        . $p('Your order is accepted when we send the order confirmation email. We may refuse or cancel an order where we suspect fraud, cannot verify the payment, or cannot deliver to the address given.')
        . $h('Delivery')
        . $p('We deliver worldwide. Delivery times, tracking, customs duties and what happens if a parcel cannot be delivered are covered in our ' . $link('shipping-policy', 'Shipping Policy') . '. The risk in the products passes to you when they are delivered.')
        . $h('All sales are final')
        . $p('Because we ship worldwide, we do not accept returns or exchanges. If your order arrives damaged, defective or with the wrong item, we replace it or refund you in full — see ' . $link('returns-and-refunds', 'Refunds &amp; Cancellations') . '.')
        . $h('Intellectual property')
        . $p('The names, logos, designs, photographs and text on this website belong to us or our licensors. You may not copy or use them without written permission.')
        . $h('Liability')
        . $p('To the extent the law allows, our total liability for any order is limited to the amount you paid for it. Nothing in these terms limits your rights as a consumer under applicable law.')
        . $h('Grievances')
        . $sc('[tl_store_block type="grievance"]')
        . $h('Governing law')
        . $p('These terms are governed by the laws of India and are subject to the jurisdiction of the courts [tl_store field="jurisdiction" before="at " fallback="in India"].')
        . $h('Contact')
        . $p('Questions about these terms? See our ' . $link('contact', 'Contact') . ' page.')
        . $p("<em>{$updated}</em>")],

    'privacy-policy' => ['Privacy Policy',
        $sc('[tl_store_block type="operator"]')
        . $p('This policy explains what personal information we collect when you visit or buy from this website, why, and the choices you have.')
        . $h('What we collect')
        . $ul(['<strong>When you order:</strong> your name, email address, phone number, billing and delivery address, the products you buy and the order amount.',
            '<strong>Payment:</strong> payments are handled by our payment partner. We receive the payment status and a reference, never your full card number or UPI PIN.',
            '<strong>Your account</strong> (if you create one): your login email and order history.',
            '<strong>When you browse:</strong> your country, estimated from your IP address, to show prices in your currency; and cookies that keep your cart, your login and your currency choice.'])
        . $h('How we use it')
        . $ul(['To process, pack, ship and track your order and send you order and delivery emails.',
            'To answer your questions and handle complaints.',
            'To issue GST invoices and keep the records Indian tax and accounting law requires.',
            'To prevent fraud and keep the website secure.'])
        . $p('We do not sell your personal information, and we do not send marketing emails unless you have asked for them.')
        . $h('Who we share it with')
        . $p('Only with the companies that help us run the store, and only what they need: our payment partner (to take payment), courier and logistics partners (your name, address and phone number, to deliver), customs authorities where a shipment requires it, and the providers that host this website and send our emails.')
        . $h('Cookies')
        . $p('We use only the cookies the store needs to work — your cart, your login session and your currency/country choice. We do not use advertising cookies.')
        . $h('How long we keep it')
        . $p('Order and invoice records are kept for as long as Indian tax and accounting law requires. Other information is deleted when it is no longer needed.')
        . $h('Your choices')
        . $p('You can ask us to show you, correct or delete the personal information we hold about you (except records we must keep by law). Write to us using the details on our ' . $link('contact', 'Contact') . ' page.')
        . $h('Grievances')
        . $sc('[tl_store_block type="grievance"]')
        . $p("<em>{$updated}</em>")],

    'returns-and-refunds' => ['Refunds & Cancellations',
        $h('No returns or exchanges')
        . $p('We ship to customers all over the world, so all sales are final: we do not accept returns or exchanges, including for size or change of mind. Please check the size guide on the product page before you order.')
        . $h('Damaged, defective or wrong items')
        . $p('If your order arrives damaged or defective, or you received the wrong item, contact us as soon as possible after delivery with your order number and photos of the item and the packaging. Once we have confirmed the problem we will, at your choice, send a replacement (if in stock) or refund you in full — at no cost to you.')
        . $h('Cancelling an order')
        . $p('You can ask us to cancel an order until it has been dispatched: contact us straight away with your order number. If the order has not left our warehouse we cancel it and refund you in full. Once an order has been dispatched it cannot be cancelled.')
        . $p('We may cancel an order ourselves if an item is unavailable or the payment cannot be verified; you then receive a full refund.')
        . $h('How refunds are paid')
        . $p('Refunds go back to the original payment method. We start the refund as soon as it is approved; banks and card issuers usually take 5–7 working days to show it on your statement. For international cards, the amount refunded is the INR amount you paid, so it may differ slightly in your currency because of exchange rates. Import duties or taxes paid to your country\'s customs cannot be refunded by us.')
        . $h('Undeliverable parcels')
        . $p('If a parcel comes back to us because the address was incomplete or wrong, or it was refused or not collected, contact us — we can re-ship it once the shipping cost is paid again. See our ' . $link('shipping-policy', 'Shipping Policy') . '.')
        . $h('Contact')
        . $p('All requests: see our ' . $link('contact', 'Contact') . ' page.')
        . $p("<em>{$updated}</em>")],

    'shipping-policy' => ['Shipping Policy',
        $h('Where we deliver')
        . $p('We deliver worldwide. Enter your address at checkout to see the delivery options and charges for your country before you pay.')
        . $h('When your order ships')
        . $p("Orders are packed at our warehouse in India and usually dispatched within {$dispatch} of payment. As soon as your order is dispatched you get an email with the courier name and tracking number.")
        . $h('Delivery times')
        . $ul(['<strong>India:</strong> [tl_store field="eta_in" after=" after dispatch" fallback="usually a few working days after dispatch, depending on your location"].',
            '<strong>Rest of the world:</strong> [tl_store field="eta_world" after=" after dispatch" fallback="depends on the destination and on customs clearance; your tracking link shows the latest estimate"].',
            'Delivery times are estimates. Customs checks, public holidays or courier delays can occasionally add a few days.'])
        . $h('Shipping charges')
        . $p('Shipping charges for your address are shown at checkout before you pay. All orders are prepaid; we do not offer Cash on Delivery.')
        . $h('Customs, duties and taxes (outside India)')
        . $p('Orders delivered outside India may be charged import duties, taxes and customs fees by the destination country. These are set by your country, are not included in our prices or shipping charges, and are paid by the recipient — usually to the courier on delivery. Please check your country\'s rules before ordering. Parcels refused because of duties cannot be refunded.')
        . $h('Tracking')
        . $p('Follow your parcel with the link in your dispatch email, or on our ' . $link('track-order', 'Track your order') . ' page with your order number and email.')
        . $h('Address and delivery problems')
        . $p('Please make sure your address and phone number are complete and correct — the courier may need to call you. If a parcel cannot be delivered because of a wrong address, or is refused or not collected, contact us; we can re-ship it once the shipping cost is paid again. If your parcel arrives damaged, see ' . $link('returns-and-refunds', 'Refunds &amp; Cancellations') . '.')
        . $p("<em>{$updated}</em>")],

    'contact' => ['Contact',
        $p('We\'re happy to help with orders, sizing, delivery or anything else. Please include your order number if you have one.')
        . $sc('[tl_store_block type="contact"]')
        . $h('Grievances')
        . $sc('[tl_store_block type="grievance"]')],

    // About: the theme's page-about.php renders the brand story from the owner's deck; the page body stays empty
    // unless someone writes their own text in WordPress.
    'about' => ['About Schermo', ''],

    'faq' => ['FAQ',
        $h('Do you ship to my country?')
        . $p('We deliver worldwide. Enter your address at checkout to see the delivery options and charges for your country.')
        . $h('How long will delivery take?')
        . $p("Orders are usually dispatched within {$dispatch} of payment. Delivery in India takes [tl_store field=\"eta_in\" fallback=\"a few working days\"] after dispatch; elsewhere it depends on the destination and customs. Details in our " . $link('shipping-policy', 'Shipping Policy') . '.')
        . $h('How do I track my order?')
        . $p('You get an email with the courier and tracking number as soon as your order ships. You can also use our ' . $link('track-order', 'Track your order') . ' page with your order number and email.')
        . $h('How can I pay?')
        . $p('Online at checkout through our secure payment partner, with the methods shown there. All orders are prepaid — we do not offer Cash on Delivery.')
        . $h('Which currency will I be charged in?')
        . $p('Prices can be shown in your currency, but every order is charged in Indian Rupees (INR). Your bank converts the amount at its own exchange rate.')
        . $h('Will I pay customs duties?')
        . $p('Orders delivered outside India may be charged import duties and taxes by your country. These are not included in our prices and are paid by the recipient, usually to the courier on delivery.')
        . $h('Can I return or exchange an item?')
        . $p('Because we ship worldwide, all sales are final and we do not accept returns or exchanges. If your item arrives damaged, defective or wrong, we replace it or refund you in full — see ' . $link('returns-and-refunds', 'Refunds &amp; Cancellations') . '.')
        . $h('How do I choose my size?')
        . $p('Every product page has a size guide. Please check it before ordering, as we cannot exchange sizes.')
        . $h('Can I cancel my order?')
        . $p('Yes, until it has been dispatched — contact us straight away with your order number and we refund you in full.')
        . $h('Do I get a GST invoice?')
        . $p('Yes. Your GST invoice can be downloaded from your order in My account and from your dispatch email.')
        . $h('How do I contact you?')
        . $p('See our ' . $link('contact', 'Contact') . ' page.')],
];

$ids = [];
$published = 0;
foreach ($pages as $slug => [$title, $content]) {
    $page = get_page_by_path($slug, OBJECT, 'page');
    $hash = md5($content);
    if (!$page) {
        $ids[$slug] = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => $content]);
        update_post_meta($ids[$slug], '_tl_generated_md5', $hash);
        $published++;
        continue;
    }
    $ids[$slug] = $page->ID;
    $ours = get_post_meta($page->ID, '_tl_generated_md5', true);
    // Untouched pages are (re)generated: our earlier drafts with [BLANKS], WordPress's sample privacy text, or a page
    // whose content is still exactly what this script wrote. Anything edited by a person is left alone.
    $untouched = str_contains($page->post_content, 'draft-note') || str_contains($page->post_content, 'Who we are') || ($ours && md5($page->post_content) === $ours);
    if ($untouched && ($ours !== $hash || $page->post_status !== 'publish')) {
        wp_update_post(['ID' => $page->ID, 'post_content' => $content, 'post_title' => $title, 'post_status' => 'publish']);
        update_post_meta($page->ID, '_tl_generated_md5', $hash);
        $published++;
    }
}
update_option('wp_page_for_privacy_policy', $ids['privacy-policy']);
if (function_exists('wc_get_page_id') && (int) get_option('woocommerce_terms_page_id') !== (int) $ids['terms-and-conditions']) {
    update_option('woocommerce_terms_page_id', $ids['terms-and-conditions']); // "I agree to the terms" link at checkout
}

// WordPress's default "Sample Page" and WooCommerce's sample returns page are removed if nobody changed them.
$sample = get_page_by_path('sample-page', OBJECT, 'page');
if ($sample && str_contains($sample->post_content, 'This is an example page')) {
    wp_trash_post($sample->ID);
}
$wc_refund = get_page_by_path('refund_returns', OBJECT, 'page');
if ($wc_refund && $wc_refund->post_status === 'draft') {
    wp_trash_post($wc_refund->ID);
}

// Footer "Policies" menu; FAQ is added to menus made before it existed.
$menu = wp_get_nav_menu_object('Policies');
if (!$menu) {
    $menu_id = wp_create_nav_menu('Policies');
    set_theme_mod('nav_menu_locations', array_merge((array) get_theme_mod('nav_menu_locations', []), ['footer-legal' => $menu_id]));
} else {
    $menu_id = $menu->term_id;
}
$in_menu = array_map('intval', array_column(wp_get_nav_menu_items($menu_id) ?: [], 'object_id'));
foreach (['faq', 'shipping-policy', 'returns-and-refunds', 'terms-and-conditions', 'privacy-policy', 'contact'] as $slug) {
    if (!in_array((int) $ids[$slug], $in_menu, true)) {
        wp_update_nav_menu_item($menu_id, 0, ['menu-item-object' => 'page', 'menu-item-object-id' => $ids[$slug], 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish']);
    }
}

// Store rules decided by the owner: prepaid only (no Cash on Delivery anywhere) and selling worldwide.
$cod = (array) get_option('woocommerce_cod_settings', []);
if (($cod['enabled'] ?? 'no') !== 'no') {
    update_option('woocommerce_cod_settings', array_merge($cod, ['enabled' => 'no']));
}
if (get_option('schermo_worldwide') !== '1') {
    update_option('woocommerce_allowed_countries', 'all');
    update_option('woocommerce_ship_to_countries', '');
    update_option('schermo_worldwide', '1');
}

// Brand tagline (owner's words); set once so a later change in Settings → General is kept.
if (get_option('schermo_tagline_set') !== '1') {
    update_option('blogdescription', 'The art of dressing');
    update_option('schermo_tagline_set', '1');
}

// Branded WooCommerce emails (only set once, so later changes in WooCommerce → Settings → Emails are kept).
if (get_option('schermo_email_branding') !== '1') {
    update_option('woocommerce_email_header_image', get_stylesheet_directory_uri() . '/assets/img/email-logo.png');
    update_option('woocommerce_email_base_color', '#0a0a0a');
    update_option('woocommerce_email_background_color', '#f2f2f0');
    update_option('woocommerce_email_body_background_color', '#ffffff');
    update_option('woocommerce_email_text_color', '#2e2e2e');
    update_option('woocommerce_email_from_name', get_bloginfo('name'));
    update_option('schermo_email_branding', '1');
}
// Email footer without the fulfilment origin (brand copy no longer mentions it); only replaces our own old text.
if (str_contains((string) get_option('woocommerce_email_footer_text'), 'Shipped from Delhi')) {
    update_option('woocommerce_email_footer_text', get_bloginfo('name') . "\nStore managed by Thoda Logic");
}
// The hub plugin's public "Track your order" page joins the Policies menu.
if (function_exists('tli_install_tracking')) {
    tli_install_tracking();
}
if ($published && function_exists('schermo_cache_clear')) {
    schermo_cache_clear();
}
echo "store pages: {$published} page(s) published/updated; prepaid only; worldwide\n";
