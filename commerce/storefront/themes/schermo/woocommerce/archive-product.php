<?php
/**
 * Schermo listing (shop, product type, collection, search):
 * breadcrumb → title and line → count → product-type navigation → filters and sorting → active filter chips → grid →
 * pagination. Collections are a separate, quieter row of links, so they never read as product types.
 * Filters live in the URL (see inc/shop-filters.php) and run on the real product query. WooCommerce hooks are kept.
 */
defined('ABSPATH') || exit;

get_header('shop');
do_action('woocommerce_before_main_content');

$term = get_queried_object();
$current = $term instanceof WP_Term && $term->taxonomy === 'product_cat' ? $term : null;
$types = schermo_types();
$colls = schermo_collection_labels();
$f = schermo_filters();
$is_coll = $current && isset($colls[$current->slug]);
$is_type = $current && isset($types[$current->slug]);
$ctx = schermo_shop_context($current);
$chips = schermo_filter_chips();
$total = (int) wc_get_loop_prop('total');
$title = woocommerce_page_title(false);
$line = $current && $current->description !== '' ? wp_strip_all_tags($current->description) : __('Sportswear, everyday pieces and team merchandise across four collections. Shipped from Delhi, India.', 'schermo');
$hero = get_stylesheet_directory_uri() . '/assets/img/hero/';
$shop = wc_get_page_permalink('shop');
$keep = array_filter(['orderby' => sanitize_key(wp_unslash($_GET['orderby'] ?? '')), 's' => get_search_query()]); // phpcs:ignore WordPress.Security.NonceVerification

// Header images: the current listing's first products, else one from each of three collections.
$stack = [];
foreach (['SC-016', 'SA-003', 'GC-001'] as $sku) {
    $pid = wc_get_product_id_by_sku($sku);
    $stack[] = $pid ? (string) wp_get_attachment_image_url((int) get_post_thumbnail_id($pid), 'large') : '';
}
if ($current) {
    $own = wc_get_products(['status' => 'publish', 'limit' => 3, 'category' => [$current->slug], 'orderby' => 'rand', 'return' => 'ids']);
    foreach ($own as $i => $pid) {
        $stack[$i] = (string) wp_get_attachment_image_url((int) get_post_thumbnail_id($pid), 'large') ?: $stack[$i];
    }
}

// Product-type links. On a collection page they narrow the collection (?type=); elsewhere they open the type page.
$type_url = static function (string $slug) use ($is_coll, $current, $f, $shop, $keep): string {
    if ($is_coll) {
        return $slug === '' ? remove_query_arg(['type', 'paged']) : add_query_arg('type', $slug, remove_query_arg('paged'));
    }
    $base = $slug === '' ? $shop : get_term_link($slug, 'product_cat');
    return add_query_arg(array_filter(['collection' => $f['collection']] + $keep), is_wp_error($base) ? $shop : $base);
};
$type_on = static fn (string $slug): bool => $is_coll ? $f['type'] === $slug : ($slug === '' ? !$is_type && $f['type'] === '' : (($is_type && $current->slug === $slug) || $f['type'] === $slug));
$type_terms = array_filter($types, static fn ($slug) => ($t = get_term_by('slug', $slug, 'product_cat')) && $t->count, ARRAY_FILTER_USE_KEY);
$active = count($chips);
?>
<section class="sx-hero container" aria-labelledby="sx-title">
  <div class="sx-panel">
    <?php echo schermo_svg('schermo-mark', 'sx-mark'); ?>
    <div class="sx-copy">
      <?php woocommerce_breadcrumb(); ?>
      <?php if ($is_coll): ?><p class="eyebrow sx-kicker"><?php esc_html_e('Collection', 'schermo'); ?></p><?php endif; ?>
      <h1 id="sx-title" class="sx-title"><?php echo esc_html($title); ?></h1>
      <p class="sx-line"><?php echo esc_html($line); ?></p>
      <p class="sx-count" aria-live="polite"><span><?php echo (int) $total; ?></span><?php echo esc_html(_n('piece', 'pieces', $total, 'schermo')); ?></p>
      <nav class="sx-colls" aria-label="<?php esc_attr_e('Collections', 'schermo'); ?>">
        <span><?php esc_html_e('Collections', 'schermo'); ?></span>
        <?php foreach ($colls as $slug => $label): $t = get_term_by('slug', $slug, 'product_cat'); if (!$t || !$t->count) { continue; } $on = $current && $current->slug === $slug; ?>
          <a href="<?php echo esc_url(get_term_link($t)); ?>"<?php echo $on ? ' class="is-on" aria-current="page"' : ''; ?>><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
    <div class="sx-stack" aria-hidden="true">
      <img class="sx-a" src="<?php echo esc_url($stack[0] ?: $hero . 'practice-shorts.webp'); ?>" alt="">
      <img class="sx-b" src="<?php echo esc_url($stack[1] ?: $hero . 'playing-jersey.webp'); ?>" alt="">
      <img class="sx-c" src="<?php echo esc_url($stack[2] ?: $hero . 'practice-kit-sleeveless.webp'); ?>" alt="">
    </div>
  </div>
</section>

<div class="sx-bar-wrap container">
  <div class="sx-bar glass">
    <nav class="sx-cats" aria-label="<?php esc_attr_e('Product type', 'schermo'); ?>">
      <a class="sx-pill<?php echo $type_on('') ? ' is-on' : ''; ?>" href="<?php echo esc_url($type_url('')); ?>"<?php echo $type_on('') ? ' aria-current="page"' : ''; ?>><?php echo esc_html($is_coll ? __('All pieces', 'schermo') : __('Shop all', 'schermo')); ?></a>
      <?php foreach ($type_terms as $slug => $label): $on = $type_on($slug); ?>
        <a class="sx-pill<?php echo $on ? ' is-on' : ''; ?>" href="<?php echo esc_url($type_url($slug)); ?>"<?php echo $on ? ' aria-current="page"' : ''; ?>><?php echo esc_html($label); ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="sx-tools">
      <button type="button" class="sx-filter-btn" data-dialog="shop-filters" aria-haspopup="dialog">
        <span class="sx-filter-ico" aria-hidden="true"></span><?php esc_html_e('Filters', 'schermo'); ?><?php if ($active): ?><span class="sx-filter-n"><?php echo (int) $active; ?></span><?php endif; ?>
      </button>
      <?php if ($chips): ?>
        <ul class="sx-chips" aria-label="<?php esc_attr_e('Active filters', 'schermo'); ?>">
          <?php foreach ($chips as [$label, $url]): ?>
            <li><a class="sx-chip" href="<?php echo esc_url($url); ?>" aria-label="<?php echo esc_attr(sprintf(__('Remove filter: %s', 'schermo'), $label)); ?>"><?php echo esc_html($label); ?><span aria-hidden="true">×</span></a></li>
          <?php endforeach; ?>
          <li><a class="sx-clear-all" href="<?php echo esc_url(schermo_clear_filters_url()); ?>"><?php esc_html_e('Clear all', 'schermo'); ?></a></li>
        </ul>
      <?php endif; ?>
      <?php if (woocommerce_product_loop()) { woocommerce_catalog_ordering(); } ?>
    </div>
  </div>
</div>

<dialog class="sheet sx-sheet" id="shop-filters" aria-labelledby="shop-filters-title">
  <form method="get" action="<?php echo esc_url(schermo_clear_filters_url()); ?>" class="sx-form">
    <div class="sheet-head"><h2 id="shop-filters-title"><?php esc_html_e('Filters', 'schermo'); ?></h2><button type="button" class="icon-btn" data-dialog-close aria-label="<?php esc_attr_e('Close', 'schermo'); ?>"><?php echo brandhouse_icon('close'); ?></button></div>
    <div class="sheet-body">
      <?php foreach ($keep as $k => $v): ?><input type="hidden" name="<?php echo esc_attr($k); ?>" value="<?php echo esc_attr($v); ?>"><?php endforeach; ?>
      <?php if (is_search()): ?><input type="hidden" name="post_type" value="product"><?php endif; ?>
      <?php if ($f['type'] !== ''): ?><input type="hidden" name="type" value="<?php echo esc_attr($f['type']); ?>"><?php endif; ?>

      <?php if (!$is_coll && count($ctx['collections']) > 1): ?>
        <fieldset class="sx-field">
          <legend><?php esc_html_e('Collection', 'schermo'); ?></legend>
          <label class="sx-opt"><input type="radio" name="collection" value=""<?php checked($f['collection'], ''); ?>><span><?php esc_html_e('All collections', 'schermo'); ?></span></label>
          <?php foreach ($ctx['collections'] as $slug => $label): ?>
            <label class="sx-opt"><input type="radio" name="collection" value="<?php echo esc_attr($slug); ?>"<?php checked($f['collection'], $slug); ?>><span><?php echo esc_html($label); ?></span></label>
          <?php endforeach; ?>
        </fieldset>
      <?php elseif ($f['collection'] !== ''): ?>
        <input type="hidden" name="collection" value="<?php echo esc_attr($f['collection']); ?>">
      <?php endif; ?>

      <?php if ($ctx['sizes']): ?>
        <fieldset class="sx-field">
          <legend><?php esc_html_e('Size', 'schermo'); ?> <small><?php esc_html_e('apparel only', 'schermo'); ?></small></legend>
          <div class="sx-size-grid">
            <?php foreach ($ctx['sizes'] as $slug => $name): ?>
              <label class="sx-size-opt"><input type="checkbox" name="filter_size[]" value="<?php echo esc_attr($slug); ?>"<?php checked(in_array($slug, $f['sizes'], true)); ?>><span><?php echo esc_html($name); ?></span></label>
            <?php endforeach; ?>
          </div>
        </fieldset>
      <?php endif; ?>

      <?php if ($ctx['range']): [$lo, $hi] = $ctx['range']; ?>
        <fieldset class="sx-field">
          <legend><?php esc_html_e('Price (₹)', 'schermo'); ?> <small><?php printf(esc_html__('priced pieces: ₹%1$s–₹%2$s', 'schermo'), esc_html(number_format_i18n($lo)), esc_html(number_format_i18n($hi))); ?></small></legend>
          <div class="sx-price">
            <label><span><?php esc_html_e('Min', 'schermo'); ?></span><input type="number" name="min_price" inputmode="numeric" min="0" step="1" placeholder="<?php echo esc_attr((string) $lo); ?>" value="<?php echo $f['min'] !== null ? esc_attr((string) (int) $f['min']) : ''; ?>"></label>
            <label><span><?php esc_html_e('Max', 'schermo'); ?></span><input type="number" name="max_price" inputmode="numeric" min="0" step="1" placeholder="<?php echo esc_attr((string) $hi); ?>" value="<?php echo $f['max'] !== null ? esc_attr((string) (int) $f['max']) : ''; ?>"></label>
          </div>
          <p class="sx-note"><?php esc_html_e('Pieces without a price yet are not included when a price is set.', 'schermo'); ?></p>
        </fieldset>
      <?php endif; ?>

      <?php if ($ctx['available']): ?>
        <fieldset class="sx-field">
          <legend><?php esc_html_e('Availability', 'schermo'); ?></legend>
          <label class="sx-opt"><input type="checkbox" name="available" value="1"<?php checked($f['available']); ?>><span><?php esc_html_e('Available to order now (priced and in stock)', 'schermo'); ?></span></label>
        </fieldset>
      <?php endif; ?>
    </div>
    <div class="sx-sheet-foot">
      <a class="btn btn-outline" href="<?php echo esc_url(schermo_clear_filters_url()); ?>"><?php esc_html_e('Clear all', 'schermo'); ?></a>
      <button type="submit" class="btn"><?php esc_html_e('Show results', 'schermo'); ?></button>
    </div>
  </form>
</dialog>

<div class="container sx-grid-wrap">
<?php
if (woocommerce_product_loop()) {
    woocommerce_output_all_notices();
    woocommerce_product_loop_start();
    $n = 0;
    $promo = get_term_by('slug', 'schermo-atelier', 'product_cat');
    while (have_posts()) {
        the_post();
        do_action('woocommerce_shop_loop');
        wc_get_template_part('content', 'product');
        if (++$n === 8 && $promo && !$is_coll && !$chips && $f['collection'] === '') {
            ?>
            <li class="sx-promo">
              <a href="<?php echo esc_url(get_term_link($promo)); ?>">
                <?php if ($stack[1]): ?><img class="sx-promo-img" src="<?php echo esc_url($stack[1]); ?>" alt="" loading="lazy"><?php endif; ?>
                <span class="sx-promo-copy"><span class="eyebrow"><?php esc_html_e('Limited drops', 'schermo'); ?></span><span class="sx-promo-title"><?php echo esc_html($promo->name); ?></span><span class="sx-promo-line"><?php esc_html_e('Limited pieces. Made to be noticed.', 'schermo'); ?></span></span>
                <span class="btn gx-gold"><?php esc_html_e('Shop the drop', 'schermo'); ?><?php echo brandhouse_icon('arrow'); ?></span>
              </a>
            </li>
            <?php
        }
    }
    woocommerce_product_loop_end();
    do_action('woocommerce_after_shop_loop');
} else {
    echo '<div class="empty-state"><p class="eyebrow">' . esc_html__('No results', 'schermo') . '</p><h2>' . esc_html__('Nothing matches these filters', 'schermo') . '</h2>';
    if ($chips) {
        echo '<p><a class="btn btn-outline" href="' . esc_url(schermo_clear_filters_url()) . '">' . esc_html__('Clear all filters', 'schermo') . '</a></p>';
    }
    echo '</div>';
}
?>
</div>
<?php
do_action('woocommerce_after_main_content');
get_footer('shop');
