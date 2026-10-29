<?php
/**
 * Product card. Image (or a labelled placeholder), title, price, in-stock sizes, one honest label.
 */
defined('ABSPATH') || exit;

global $product;
if (!$product instanceof WC_Product || !$product->is_visible()) {
    return;
}
$label = brandhouse_card_label($product);
$sizes = brandhouse_card_sizes($product);
$cat = wc_get_product_category_list($product->get_id(), ', ');
?>
<li <?php wc_product_class('card', $product); ?>>
  <a class="card-media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
    <?php
    if ($product->get_image_id()) {
        echo $product->get_image('woocommerce_thumbnail', ['loading' => 'lazy', 'sizes' => '(max-width: 760px) 50vw, 25vw']);
        $gallery = $product->get_gallery_image_ids();
        if ($gallery) {
            echo wp_get_attachment_image($gallery[0], 'woocommerce_thumbnail', false, ['class' => 'alt', 'loading' => 'lazy', 'alt' => '']);
        }
    } else {
        echo brandhouse_placeholder(__('Product photo', 'brandhouse'));
    }
    ?>
    <?php if ($label): ?><span class="tag<?php echo !$product->is_in_stock() ? ' tag-muted' : ''; ?>"><?php echo esc_html($label); ?></span><?php endif; ?>
  </a>
  <div class="card-body">
    <?php if ($cat): ?><p class="card-cat"><?php echo wp_strip_all_tags($cat); ?></p><?php endif; ?>
    <h2 class="card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
    <p class="card-price"><?php echo $product->get_price_html(); ?></p>
    <?php if ($sizes): ?>
      <p class="card-sizes" aria-label="<?php esc_attr_e('Sizes', 'brandhouse'); ?>">
        <?php foreach ($sizes as $s): ?><span class="<?php echo $s['in'] ? '' : 'out'; ?>"<?php echo $s['in'] ? '' : ' title="' . esc_attr__('Sold out', 'brandhouse') . '"'; ?>><?php echo esc_html($s['label']); ?></span><?php endforeach; ?>
      </p>
    <?php endif; ?>
  </div>
</li>
