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

// Products the owner has not priced yet say so instead of showing nothing.
$brandhouse_no_price = static fn (): string => '<span class="price-soon">' . esc_html__('Coming soon', 'brandhouse') . '</span>';
add_filter('woocommerce_empty_price_html', $brandhouse_no_price);
add_filter('woocommerce_variable_empty_price_html', $brandhouse_no_price);
add_filter('woocommerce_out_of_stock_message', static function (string $msg): string {
    global $product;
    return $product instanceof WC_Product && $product->get_price() === '' ? '' : $msg;
});

// Menu links to product categories that have no products are hidden (e.g. after products are removed).
// Links to pages that are not published (e.g. policy drafts waiting for the owner) are hidden too, so they never 404.
add_filter('wp_nav_menu_objects', static fn (array $items): array => array_values(array_filter($items, static function ($i): bool {
    if ($i->object === 'page') {
        return get_post_status((int) $i->object_id) === 'publish';
    }
    if ($i->object !== 'product_cat') {
        return true;
    }
    $t = get_term((int) $i->object_id, 'product_cat');
    return $t instanceof WP_Term && $t->count > 0;
})));

add_filter('loop_shop_per_page', static fn (): int => 24);
add_filter('loop_shop_columns', static fn (): int => 4);

// The struck-through MRP beside the price already shows a sale; WooCommerce's extra "Sale!" text is dropped.
remove_action('woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10);
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

// Related products and upsells sit below the product, outside the grid, so the sticky summary never overlaps them.
remove_action('woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15);
remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20);
add_action('woocommerce_after_single_product', 'woocommerce_upsell_display', 15);
add_action('woocommerce_after_single_product', 'woocommerce_output_related_products', 20);

// Products without a price cannot be bought, so WooCommerce hides the buy form. Show the sizes and the buttons
// anyway, disabled, so the page reads complete; they work as soon as a price is set.
add_action('woocommerce_single_product_summary', static function (): void {
    global $product;
    if (!$product instanceof WC_Product || $product->get_price() !== '' || $product->is_purchasable()) {
        return;
    }
    echo '<div class="preorder-preview">';
    if ($product->is_type('variable')) {
        $sizes = wc_get_product_terms($product->get_id(), 'pa_size', ['fields' => 'names']);
        if ($sizes) {
            echo '<p class="preview-label">' . esc_html__('Size', 'brandhouse') . '</p><div class="size-options" role="radiogroup" aria-label="' . esc_attr__('Size', 'brandhouse') . '">';
            foreach ($sizes as $i => $s) {
                printf('<button type="button" class="size-opt" role="radio" aria-checked="%s" data-preview-size>%s</button>', $i === 0 ? 'true' : 'false', esc_html($s));
            }
            echo '</div>';
            brandhouse_size_guide_link();
        }
    }
    echo '<div class="preview-actions"><button type="button" class="button single_add_to_cart_button" disabled>' . esc_html__('Add to cart', 'brandhouse') . '</button>';
    echo '<button type="button" class="button buy-now btn-outline" disabled>' . esc_html__('Buy now', 'brandhouse') . '</button></div>';
    echo '<p class="preview-note">' . esc_html__('Ordering opens when the price is announced.', 'brandhouse') . '</p></div>';
}, 30);

add_filter('woocommerce_output_related_products_args', static fn (array $a): array => array_merge($a, ['posts_per_page' => 4, 'columns' => 4]));

function brandhouse_size_guide_link(): void
{
    global $product;
    if (!$product instanceof WC_Product || !$product->is_type('variable') || !array_key_exists('pa_size', $product->get_variation_attributes())) {
        return;
    }
    echo '<button type="button" class="link-btn size-guide-open" data-dialog="size-guide">' . brandhouse_icon('ruler') . esc_html__('Size guide', 'brandhouse') . '</button>';
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
        echo '<div class="pdp-desc">' . apply_filters('the_content', $desc) . '</div>'; // phpcs:ignore -- post content
    }
    $attrs = array_filter($product->get_attributes(), static fn ($a) => $a->get_visible());
    if ($attrs || $product->get_sku()) {
        echo '<h2 class="pdp-sub">' . esc_html__('Specifications', 'brandhouse') . '</h2>';
        echo '<dl class="spec pdp-spec">';
        if ($product->get_sku()) {
            echo '<dt>' . esc_html__('SKU', 'brandhouse') . '</dt><dd>' . esc_html($product->get_sku()) . '</dd>';
        }
        foreach ($attrs as $a) {
            echo '<dt>' . esc_html(wc_attribute_label($a->get_name())) . '</dt><dd>' . esc_html(implode(', ', wc_get_product_terms($product->get_id(), $a->get_name(), ['fields' => 'names']) ?: $a->get_options())) . '</dd>';
        }
        echo '</dl>';
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

/**
 * One small card label, from real data only, in this order: Sold out (priced and out of stock), anything a child
 * theme adds (e.g. Limited for a limited collection), Sale (a real sale price), New (published in the last 30 days —
 * shown only while fewer than half of the catalogue is that new, otherwise every card would say it).
 */
function brandhouse_card_label(WC_Product $p): ?string
{
    if ($p->get_price() !== '' && !$p->is_in_stock()) {
        return __('Sold out', 'brandhouse');
    }
    $own = apply_filters('brandhouse_card_label', null, $p);
    if ($own) {
        return (string) $own;
    }
    if ($p->is_on_sale()) {
        return __('Sale', 'brandhouse');
    }
    $created = $p->get_date_created();
    if ($created && $created->getTimestamp() > time() - 30 * DAY_IN_SECONDS && brandhouse_new_is_meaningful()) {
        return __('New', 'brandhouse');
    }
    return null;
}

function brandhouse_new_is_meaningful(): bool
{
    static $ok = null;
    if ($ok === null) {
        $all = (int) (wp_count_posts('product')->publish ?? 0);
        $new = count(wc_get_products(['status' => 'publish', 'limit' => -1, 'return' => 'ids', 'date_created' => '>' . (time() - 30 * DAY_IN_SECONDS)]));
        $ok = $all > 0 && $new * 2 < $all;
    }
    return $ok;
}

/** The quiet line above a card's name. Default: the product's first category; a child theme can refine it. */
function brandhouse_card_context(WC_Product $p): string
{
    $terms = get_the_terms($p->get_id(), 'product_cat');
    $default = $terms && !is_wp_error($terms) ? $terms[0]->name : '';
    return (string) apply_filters('brandhouse_card_context', $default, $p);
}

/**
 * Quick add data for a card, from real WooCommerce data only: null unless the product can be ordered now.
 * Variable products list each size with its variation ID and whether it can be bought; others add directly.
 */
function brandhouse_quick_add_data(WC_Product $p): ?array
{
    if (!$p->is_purchasable() || !$p->is_in_stock() || $p->get_price() === '') {
        return null;
    }
    $data = ['id' => $p->get_id(), 'name' => $p->get_name(), 'url' => $p->get_permalink(), 'price' => wp_strip_all_tags(html_entity_decode($p->get_price_html())), 'image' => (string) wp_get_attachment_image_url((int) $p->get_image_id(), 'woocommerce_thumbnail'), 'sizes' => []];
    if ($p->is_type('variable')) {
        $order = ['s', 'm', 'l', 'xl', 'xxl', 'xxxl'];
        foreach ($p->get_available_variations('objects') as $v) {
            $slug = (string) ($v->get_attributes()['pa_size'] ?? '');
            if ($slug === '') {
                return null; // a variation without a size: let the product page handle the choice
            }
            $term = get_term_by('slug', $slug, 'pa_size');
            $data['sizes'][$slug] = ['label' => $term ? $term->name : strtoupper($slug), 'id' => $v->get_id(), 'ok' => $v->is_purchasable() && $v->is_in_stock()];
        }
        if (!$data['sizes']) {
            return null;
        }
        uksort($data['sizes'], static fn ($a, $b) => ((($i = array_search($a, $order, true)) === false ? 99 : $i) <=> (($j = array_search($b, $order, true)) === false ? 99 : $j)));
        $data['sizes'] = array_values($data['sizes']);
    }
    return $data;
}

// The Quick add sheet and a small status toast, once per page that lists products.
add_action('wp_footer', static function (): void {
    if (is_admin() || is_checkout() || is_cart()) {
        return;
    }
    ?>
    <dialog class="sheet qa-sheet" id="quick-add" aria-labelledby="qa-title">
      <div class="sheet-head"><h2 id="qa-title"><?php esc_html_e('Choose a size', 'brandhouse'); ?></h2><button type="button" class="icon-btn" data-dialog-close aria-label="<?php esc_attr_e('Close', 'brandhouse'); ?>"><?php echo brandhouse_icon('close'); ?></button></div>
      <div class="sheet-body">
        <div class="qa-prod"><img class="qa-img" alt="" width="96" height="120"><div><p class="qa-name"></p><p class="qa-price"></p></div></div>
        <fieldset class="qa-sizes"><legend class="sr-only"><?php esc_html_e('Size', 'brandhouse'); ?></legend><div class="qa-grid"></div></fieldset>
        <p class="qa-msg" role="status" aria-live="polite"></p>
        <div class="qa-actions"><button type="button" class="btn qa-add" disabled><?php esc_html_e('Add to bag', 'brandhouse'); ?></button><a class="btn btn-outline qa-more" href="#"><?php esc_html_e('View details', 'brandhouse'); ?></a></div>
        <a class="link-btn qa-bag" href="<?php echo esc_url(wc_get_cart_url()); ?>" hidden><?php esc_html_e('View bag', 'brandhouse'); ?></a>
      </div>
    </dialog>
    <div class="bh-toast" role="status" aria-live="polite" hidden></div>
    <?php
}, 30);

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
