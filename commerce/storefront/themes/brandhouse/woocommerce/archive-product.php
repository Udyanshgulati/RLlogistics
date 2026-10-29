<?php
/**
 * Product listing (shop, category, search). Overrides WooCommerce's archive-product.php — keeps its hooks for plugins.
 * Filters are built from the real catalogue: categories that have products, sizes that exist as attribute terms.
 */
defined('ABSPATH') || exit;

get_header('shop');
do_action('woocommerce_before_main_content');

$term = get_queried_object();
$parent = $term instanceof WP_Term && $term->taxonomy === 'product_cat' ? $term->term_id : 0;
$cats = get_terms(['taxonomy' => 'product_cat', 'parent' => $parent, 'hide_empty' => true, 'exclude' => [(int) get_option('default_product_cat')]]);
$sizes = taxonomy_exists('pa_size') ? get_terms(['taxonomy' => 'pa_size', 'hide_empty' => true]) : [];
$chosen = array_filter(explode(',', sanitize_text_field(wp_unslash($_GET['filter_size'] ?? ''))));
$order = ['s', 'm', 'l', 'xl', 'xxl', 'xxxl'];
if (is_array($sizes)) {
    usort($sizes, static fn ($a, $b) => ((($i = array_search($a->slug, $order, true)) === false ? 99 : $i) <=> (($j = array_search($b->slug, $order, true)) === false ? 99 : $j)));
}
$sizeUrl = static function (string $slug) use ($chosen): string {
    $next = in_array($slug, $chosen, true) ? array_diff($chosen, [$slug]) : array_merge($chosen, [$slug]);
    $url = remove_query_arg(['filter_size', 'query_type_size', 'paged']);
    return $next ? add_query_arg(['filter_size' => implode(',', $next), 'query_type_size' => 'or'], $url) : $url;
};
?>
<header class="shop-head container">
  <?php woocommerce_breadcrumb(); ?>
  <div class="shop-title">
    <h1 class="page-title"><?php woocommerce_page_title(); ?></h1>
    <?php if (wc_get_loop_prop('total')): ?><span class="count"><?php echo esc_html(sprintf(_n('%d product', '%d products', (int) wc_get_loop_prop('total'), 'brandhouse'), (int) wc_get_loop_prop('total'))); ?></span><?php endif; ?>
  </div>
  <?php do_action('woocommerce_archive_description'); ?>
</header>

<div class="shop-bar container">
  <?php if (is_array($cats) && $cats): ?>
    <nav class="chips" aria-label="<?php esc_attr_e('Categories', 'brandhouse'); ?>">
      <?php foreach ($cats as $c): ?><a class="chip" href="<?php echo esc_url(get_term_link($c)); ?>"><?php echo esc_html($c->name); ?></a><?php endforeach; ?>
    </nav>
  <?php endif; ?>
  <div class="shop-controls">
    <?php if (is_array($sizes) && $sizes): ?>
      <div class="size-filter" role="group" aria-label="<?php esc_attr_e('Filter by size', 'brandhouse'); ?>">
        <span class="label"><?php esc_html_e('Size', 'brandhouse'); ?></span>
        <?php foreach ($sizes as $s): $on = in_array($s->slug, $chosen, true); ?>
          <a class="size-chip<?php echo $on ? ' is-on' : ''; ?>" href="<?php echo esc_url($sizeUrl($s->slug)); ?>" aria-pressed="<?php echo $on ? 'true' : 'false'; ?>"><?php echo esc_html($s->name); ?></a>
        <?php endforeach; ?>
        <?php if ($chosen): ?><a class="link-btn" href="<?php echo esc_url(remove_query_arg(['filter_size', 'query_type_size', 'paged'])); ?>"><?php esc_html_e('Clear', 'brandhouse'); ?></a><?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if (woocommerce_product_loop()) { woocommerce_catalog_ordering(); } ?>
  </div>
</div>

<div class="container">
<?php
if (woocommerce_product_loop()) {
    woocommerce_output_all_notices();
    woocommerce_product_loop_start();
    if (wc_get_loop_prop('total')) {
        while (have_posts()) {
            the_post();
            do_action('woocommerce_shop_loop');
            wc_get_template_part('content', 'product');
        }
    }
    woocommerce_product_loop_end();
    do_action('woocommerce_after_shop_loop');
} else {
    echo '<div class="empty-state"><p class="eyebrow">' . esc_html__('No results', 'brandhouse') . '</p><h2>' . esc_html__('Nothing matches these filters', 'brandhouse') . '</h2>';
    if ($chosen) {
        echo '<p><a class="btn btn-outline" href="' . esc_url(remove_query_arg(['filter_size', 'query_type_size', 'paged'])) . '">' . esc_html__('Clear size filter', 'brandhouse') . '</a></p>';
    }
    echo '</div>';
}
?>
</div>
<?php
do_action('woocommerce_after_main_content');
get_footer('shop');
