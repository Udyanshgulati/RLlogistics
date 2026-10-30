<?php
/**
 * Schermo listing (shop, category, search): bento hero, one sticky glass toolbar, uniform product grid and a
 * Milan Icons row after the eighth product. Keeps WooCommerce's hooks so plugins still work.
 */
defined('ABSPATH') || exit;

get_header('shop');
do_action('woocommerce_before_main_content');

$term = get_queried_object();
$current = $term instanceof WP_Term && $term->taxonomy === 'product_cat' ? $term : null;
$cats = get_terms(['taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => true, 'exclude' => [(int) get_option('default_product_cat')]]);
$sizes = taxonomy_exists('pa_size') ? get_terms(['taxonomy' => 'pa_size', 'hide_empty' => true]) : [];
$chosen = array_filter(explode(',', sanitize_text_field(wp_unslash($_GET['filter_size'] ?? '')))); // phpcs:ignore WordPress.Security.NonceVerification
$order = ['s', 'm', 'l', 'xl', 'xxl', 'xxxl'];
if (is_array($sizes)) {
    usort($sizes, static fn ($a, $b) => ((($i = array_search($a->slug, $order, true)) === false ? 99 : $i) <=> (($j = array_search($b->slug, $order, true)) === false ? 99 : $j)));
}
$sizeUrl = static function (string $slug) use ($chosen): string {
    $next = in_array($slug, $chosen, true) ? array_diff($chosen, [$slug]) : array_merge($chosen, [$slug]);
    $url = remove_query_arg(['filter_size', 'query_type_size', 'paged']);
    return $next ? add_query_arg(['filter_size' => implode(',', $next), 'query_type_size' => 'or'], $url) : $url;
};
$total = (int) wc_get_loop_prop('total');
$title = woocommerce_page_title(false);
$line = $current && $current->description !== '' ? wp_strip_all_tags($current->description) : __('Kits, travel wear and accessories — shipped from Delhi, India.', 'schermo');
$hero = get_stylesheet_directory_uri() . '/assets/img/hero/';
$collection = get_term_by('slug', 'milan-icons', 'product_cat');
?>
<section class="sx-hero container" aria-labelledby="sx-title">
  <div class="sx-panel">
    <?php echo schermo_svg('schermo-mark', 'sx-mark'); ?>
    <div class="sx-copy">
      <?php woocommerce_breadcrumb(); ?>
      <h1 id="sx-title" class="sx-title"><?php echo esc_html($title); ?></h1>
      <p class="sx-line"><?php echo esc_html($line); ?></p>
      <?php if ($total): ?><p class="sx-count"><span><?php echo (int) $total; ?></span><?php echo esc_html(_n('piece', 'pieces', $total, 'schermo')); ?></p><?php endif; ?>
    </div>
    <div class="sx-stack" aria-hidden="true">
      <img class="sx-a" src="<?php echo esc_url($hero . 'practice-shorts.webp'); ?>" alt="">
      <img class="sx-b" src="<?php echo esc_url($hero . 'playing-jersey.webp'); ?>" alt="">
      <img class="sx-c" src="<?php echo esc_url($hero . 'practice-kit-sleeveless.webp'); ?>" alt="">
    </div>
  </div>
</section>

<div class="sx-bar-wrap container">
  <div class="sx-bar glass">
    <?php if (is_array($cats) && $cats): ?>
      <nav class="sx-cats" aria-label="<?php esc_attr_e('Categories', 'schermo'); ?>">
        <a class="sx-pill<?php echo $current ? '' : ' is-on'; ?>" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>"<?php echo $current ? '' : ' aria-current="page"'; ?>><?php esc_html_e('All', 'schermo'); ?></a>
        <?php foreach ($cats as $c): $on = $current && $current->term_id === $c->term_id; ?>
          <a class="sx-pill<?php echo $on ? ' is-on' : ''; ?>" href="<?php echo esc_url(get_term_link($c)); ?>"<?php echo $on ? ' aria-current="page"' : ''; ?>><?php echo esc_html($c->name); ?></a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>
    <div class="sx-tools">
      <?php if (is_array($sizes) && $sizes): ?>
        <div class="sx-sizes" role="group" aria-label="<?php esc_attr_e('Filter by size', 'schermo'); ?>">
          <?php foreach ($sizes as $s): $on = in_array($s->slug, $chosen, true); ?>
            <a class="sx-size<?php echo $on ? ' is-on' : ''; ?>" href="<?php echo esc_url($sizeUrl($s->slug)); ?>" aria-pressed="<?php echo $on ? 'true' : 'false'; ?>"><?php echo esc_html($s->name); ?></a>
          <?php endforeach; ?>
          <?php if ($chosen): ?><a class="sx-clear" href="<?php echo esc_url(remove_query_arg(['filter_size', 'query_type_size', 'paged'])); ?>" aria-label="<?php esc_attr_e('Clear size filter', 'schermo'); ?>"><?php echo brandhouse_icon('close'); ?></a><?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if (woocommerce_product_loop()) { woocommerce_catalog_ordering(); } ?>
    </div>
  </div>
</div>

<div class="container sx-grid-wrap">
<?php
if (woocommerce_product_loop()) {
    woocommerce_output_all_notices();
    woocommerce_product_loop_start();
    $n = 0;
    while (have_posts()) {
        the_post();
        do_action('woocommerce_shop_loop');
        wc_get_template_part('content', 'product');
        if (++$n === 8 && $collection && !is_wp_error($collection) && (!$current || $current->slug !== 'milan-icons')) {
            ?>
            <li class="sx-promo">
              <a href="<?php echo esc_url(get_term_link($collection)); ?>">
                <img src="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/img/milan-icons-crest.jpg'); ?>" alt="" loading="lazy">
                <span class="sx-promo-copy"><span class="eyebrow"><?php esc_html_e('Collection', 'schermo'); ?></span><span class="sx-promo-title"><?php echo esc_html($collection->name); ?></span><span class="sx-promo-line">Play Beyond · Sempre Avanti</span></span>
                <span class="btn gx-gold"><?php esc_html_e('Shop the collection', 'schermo'); ?><?php echo brandhouse_icon('arrow'); ?></span>
              </a>
            </li>
            <?php
        }
    }
    woocommerce_product_loop_end();
    do_action('woocommerce_after_shop_loop');
} else {
    echo '<div class="empty-state"><p class="eyebrow">' . esc_html__('No results', 'schermo') . '</p><h2>' . esc_html__('Nothing matches these filters', 'schermo') . '</h2>';
    if ($chosen) {
        echo '<p><a class="btn btn-outline" href="' . esc_url(remove_query_arg(['filter_size', 'query_type_size', 'paged'])) . '">' . esc_html__('Clear size filter', 'schermo') . '</a></p>';
    }
    echo '</div>';
}
?>
</div>
<?php
do_action('woocommerce_after_main_content');
get_footer('shop');
