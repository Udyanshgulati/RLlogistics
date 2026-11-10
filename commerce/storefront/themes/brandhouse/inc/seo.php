<?php
/**
 * Search and sharing basics: a meta description and Open Graph / Twitter tags on every page, so links shared on
 * WhatsApp, Instagram or X show the product photo, name and a short description. Skipped when an SEO plugin is active.
 */

defined('ABSPATH') || exit;

function brandhouse_seo_plugin_active(): bool
{
    return defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('AIOSEO_VERSION') || defined('SEOPRESS_VERSION');
}

/** Title, description, image and type for the current page. */
function brandhouse_share_data(): array
{
    $site = get_bloginfo('name');
    $clip = static fn (string $t): string => mb_strimwidth(trim(preg_replace('/\s+/', ' ', wp_strip_all_tags(strip_shortcodes($t)))), 0, 160, '…');
    $d = ['title' => wp_get_document_title(), 'desc' => '', 'image' => '', 'type' => 'website', 'url' => ''];
    if (function_exists('is_product') && is_product() && ($p = wc_get_product(get_queried_object_id()))) {
        $d['desc'] = $clip($p->get_short_description() ?: $p->get_description());
        $d['image'] = $p->get_image_id() ? (string) wp_get_attachment_image_url($p->get_image_id(), 'large') : '';
        $d['type'] = 'product';
        $d['url'] = get_permalink($p->get_id());
        if ($p->get_price() !== '') {
            $d['price'] = wc_format_decimal($p->get_price(), wc_get_price_decimals());
        }
    } elseif (function_exists('is_product_category') && (is_product_category() || is_product_tag())) {
        $t = get_queried_object();
        $d['desc'] = $clip((string) term_description($t));
        $thumb = $t instanceof WP_Term ? (int) get_term_meta($t->term_id, 'thumbnail_id', true) : 0;
        $d['image'] = $thumb ? (string) wp_get_attachment_image_url($thumb, 'large') : '';
        $d['url'] = $t instanceof WP_Term ? (string) get_term_link($t) : '';
    } elseif (is_singular()) {
        $post = get_queried_object();
        $d['desc'] = $clip((string) ($post->post_excerpt ?: $post->post_content));
        $d['image'] = has_post_thumbnail($post) ? (string) get_the_post_thumbnail_url($post, 'large') : '';
        $d['url'] = get_permalink($post);
    } elseif (function_exists('is_shop') && is_shop()) {
        $d['url'] = get_permalink(wc_get_page_id('shop'));
    }
    if ($d['desc'] === '') {
        $d['desc'] = get_bloginfo('description') ?: sprintf('%s — official store. Shipped from Delhi, India.', $site);
    }
    if ($d['image'] === '') {
        $file = get_stylesheet_directory() . '/assets/img/share.jpg';
        $d['image'] = file_exists($file) ? get_stylesheet_directory_uri() . '/assets/img/share.jpg' : (string) get_site_icon_url(512);
    }
    if ($d['url'] === '') {
        $d['url'] = is_front_page() ? home_url('/') : '';
    }
    return $d;
}

add_action('wp_head', static function (): void {
    if (brandhouse_seo_plugin_active() || is_404() || is_search() || (function_exists('is_checkout') && (is_cart() || is_checkout() || is_account_page()))) {
        return;
    }
    $d = brandhouse_share_data();
    printf('<meta name="description" content="%s">' . "\n", esc_attr($d['desc']));
    $tags = ['og:site_name' => get_bloginfo('name'), 'og:type' => $d['type'], 'og:title' => $d['title'], 'og:description' => $d['desc'], 'og:url' => $d['url'], 'og:image' => $d['image'], 'og:locale' => 'en_IN'];
    if (!empty($d['price'])) {
        $tags['product:price:amount'] = $d['price'];
        $tags['product:price:currency'] = get_woocommerce_currency();
    }
    foreach ($tags as $k => $v) {
        if ($v !== '') {
            printf('<meta property="%s" content="%s">' . "\n", esc_attr($k), esc_attr($v));
        }
    }
    printf('<meta name="twitter:card" content="%s">' . "\n", $d['image'] ? 'summary_large_image' : 'summary');
}, 3);
