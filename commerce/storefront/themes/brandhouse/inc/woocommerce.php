<?php
/**
 * WooCommerce integration. Uses WooCommerce's own templates and hooks; checkout is never rebuilt.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

if (!class_exists('WooCommerce')) {
    return;
}

// The theme styles classic WooCommerce markup itself; block cart/checkout keep their block styles.
add_filter('woocommerce_enqueue_styles', '__return_empty_array');

// Our own page wrappers and no sidebar.
remove_action('woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10);
remove_action('woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10);
remove_action('woocommerce_sidebar', 'woocommerce_get_sidebar', 10);
add_action('woocommerce_before_main_content', static fn () => print('<main id="main" class="shop-main">'), 10);
add_action('woocommerce_after_main_content', static fn () => print('</main>'), 10);

add_filter('woocommerce_breadcrumb_defaults', static fn (array $d): array => array_merge($d, [
    'delimiter' => '<span class="sep" aria-hidden="true">/</span>',
    'wrap_before' => '<nav class="crumbs" aria-label="Breadcrumb">',
    'wrap_after' => '</nav>',
]));

// The listing template prints its own breadcrumb inside the shop header.
add_action('woocommerce_before_main_content', static function (): void {
    if (!is_product()) {
        remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20);
    }
}, 1);

add_filter('loop_shop_per_page', static fn (): int => 24);
add_filter('loop_shop_columns', static fn (): int => 4);

// Product page: description and service details in accordions instead of tabs.
remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10);
add_action('woocommerce_single_product_summary', 'brandhouse_product_details', 45);
add_action('woocommerce_before_add_to_cart_button', 'brandhouse_size_guide_link', 5);
// No product photo yet: a labelled placeholder instead of WooCommerce's generic picture icon.
add_filter('woocommerce_single_product_image_thumbnail_html', static fn (string $html, int $id): string => $id ? $html : '<div class="woocommerce-product-gallery__image--placeholder">' . brandhouse_placeholder(__('Product photo', 'brandhouse')) . '</div>', 10, 2);
// "Buy now" beside "Add to cart": same form, then straight to checkout.
add_action('woocommerce_after_add_to_cart_button', static function (): void {
    global $product;
    if ($product instanceof WC_Product && $product->is_type('simple')) {
        // Simple products submit their ID on the main button only; the hidden field lets "Buy now" add it too.
        printf('<input type="hidden" name="add-to-cart" value="%d">', (int) $product->get_id());
    }
    echo '<button type="submit" name="bh_buy_now" value="1" class="button buy-now btn-outline">' . esc_html__('Buy now', 'brandhouse') . '</button>';
});
add_filter('woocommerce_add_to_cart_redirect', static fn ($url) => !empty($_REQUEST['bh_buy_now']) ? wc_get_checkout_url() : $url); // phpcs:ignore WordPress.Security.NonceVerification -- only chooses the redirect target

add_filter('woocommerce_output_related_products_args', static fn (array $a): array => array_merge($a, ['posts_per_page' => 4, 'columns' => 4]));

function brandhouse_size_guide_link(): void
{
    global $product;
    if (!$product instanceof WC_Product || !$product->is_type('variable') || !array_key_exists('pa_size', $product->get_variation_attributes())) {
        return;
    }
    echo '<button type="button" class="link-btn size-guide-open" data-dialog="size-guide">' . esc_html__('Size guide', 'brandhouse') . '</button>';
    add_action('wp_footer', 'brandhouse_size_guide_dialog');
}

/**
 * Size guide. Measurements are client data: until supplied, the dialog says so instead of showing invented numbers.
 * Supply them in Customize → Product pages → Size guide (HTML table).
 */
function brandhouse_size_guide_dialog(): void
{
    $table = get_theme_mod('brandhouse_size_guide', '');
    echo '<dialog class="sheet" id="size-guide" aria-labelledby="size-guide-title"><div class="sheet-head"><h2 id="size-guide-title">' . esc_html__('Size guide', 'brandhouse') . '</h2><button type="button" class="icon-btn" data-dialog-close aria-label="' . esc_attr__('Close', 'brandhouse') . '">' . brandhouse_icon('close') . '</button></div><div class="sheet-body">';
    echo $table !== '' ? wp_kses_post($table) : '<p class="placeholder-note">' . esc_html__('Measurements for each size (S–XXXL) will appear here once supplied.', 'brandhouse') . '</p>';
    echo '</div></dialog>';
}

function brandhouse_product_details(): void
{
    global $product;
    $sections = [];
    $desc = $product->get_description();
    if ($desc !== '') {
        $sections[] = [__('Details', 'brandhouse'), apply_filters('the_content', $desc), true];
    }
    $attrs = array_filter($product->get_attributes(), static fn ($a) => $a->get_visible());
    if ($attrs || $product->get_sku()) {
        ob_start();
        echo '<dl class="spec">';
        if ($product->get_sku()) {
            echo '<dt>' . esc_html__('SKU', 'brandhouse') . '</dt><dd>' . esc_html($product->get_sku()) . '</dd>';
        }
        foreach ($attrs as $a) {
            echo '<dt>' . esc_html(wc_attribute_label($a->get_name())) . '</dt><dd>' . esc_html(implode(', ', wc_get_product_terms($product->get_id(), $a->get_name(), ['fields' => 'names']) ?: $a->get_options())) . '</dd>';
        }
        echo '</dl>';
        $sections[] = [__('Specifications', 'brandhouse'), (string) ob_get_clean(), false];
    }
    $sections[] = [__('Delivery', 'brandhouse'), wpautop(esc_html(brandhouse_mod('brandhouse_delivery_text', __('Ships from our warehouse in Delhi, India. Available express services, delivery estimates and any import charges are shown at checkout for your address.', 'brandhouse')))), false];
    $sections[] = [__('Returns', 'brandhouse'), wpautop(esc_html(brandhouse_mod('brandhouse_returns_text', __('Returns policy to be confirmed.', 'brandhouse')))), false];
    echo '<div class="accordions">';
    foreach ($sections as [$title, $html, $open]) {
        printf('<details class="acc"%s><summary>%s</summary><div class="acc-body">%s</div></details>', $open ? ' open' : '', esc_html($title), $html); // phpcs:ignore -- built from escaped parts
    }
    echo '</div>';
}

// Keep the header bag count in sync after AJAX add-to-cart.
add_filter('woocommerce_add_to_cart_fragments', static function (array $f): array {
    $f['span.bag-count'] = brandhouse_bag_count();
    return $f;
});

function brandhouse_bag_count(): string
{
    $n = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
    return '<span class="bag-count' . ($n ? '' : ' is-empty') . '" aria-label="' . esc_attr(sprintf(_n('%d item in bag', '%d items in bag', $n, 'brandhouse'), $n)) . '">' . (int) $n . '</span>';
}

/** Shows a small label on product cards from real data only: sale, sold out, or new (published in the last 30 days). */
function brandhouse_card_label(WC_Product $p): ?string
{
    if (!$p->is_in_stock()) {
        return __('Sold out', 'brandhouse');
    }
    if ($p->is_on_sale()) {
        return __('Sale', 'brandhouse');
    }
    $created = $p->get_date_created();
    if ($created && $created->getTimestamp() > time() - 30 * DAY_IN_SECONDS) {
        return __('New', 'brandhouse');
    }
    return null;
}

/** Sizes in stock for a variable product, from WooCommerce's variation data (never hard-coded). */
function brandhouse_card_sizes(WC_Product $p): array
{
    if (!$p->is_type('variable')) {
        return [];
    }
    $order = ['s', 'm', 'l', 'xl', 'xxl', 'xxxl'];
    $sizes = [];
    foreach ($p->get_children() as $vid) {
        $v = wc_get_product($vid);
        if (!$v) {
            continue;
        }
        $slug = $v->get_attribute('pa_size') ?: '';
        if ($slug !== '') {
            $sizes[strtolower($slug)] = ['label' => strtoupper($slug), 'in' => $v->is_in_stock()];
        }
    }
    $rank = static fn (string $k): int => ($i = array_search($k, $order, true)) === false ? 99 : $i;
    uksort($sizes, static fn ($a, $b) => $rank($a) <=> $rank($b));
    return array_values($sizes);
}

// Customizer: placeholders the client fills in, never invented content.
add_action('customize_register', static function (WP_Customize_Manager $c): void {
    $c->add_section('brandhouse_product', ['title' => __('Product pages', 'brandhouse'), 'priority' => 160]);
    foreach ([
        'brandhouse_delivery_text' => __('Delivery text', 'brandhouse'),
        'brandhouse_returns_text' => __('Returns text', 'brandhouse'),
        'brandhouse_size_guide' => __('Size guide (HTML table)', 'brandhouse'),
    ] as $id => $label) {
        $c->add_setting($id, ['sanitize_callback' => $id === 'brandhouse_size_guide' ? 'wp_kses_post' : 'sanitize_textarea_field']);
        $c->add_control($id, ['label' => $label, 'section' => 'brandhouse_product', 'type' => 'textarea']);
    }
});
