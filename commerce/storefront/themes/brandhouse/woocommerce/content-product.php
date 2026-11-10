<?php
/**
 * Product card: image (second view on hover only when the product has one), a quiet collection/type line, name,
 * real price (or "Coming soon") and at most one honest label. Pieces that can be ordered get a Quick add button:
 * sized pieces open a sheet with their real sizes (a size must be chosen); one-size pieces go straight to the bag.
 * The whole card is one link (the title's link is stretched over the card), so it is a single tab stop.
 */
defined('ABSPATH') || exit;

global $product;
if (!$product instanceof WC_Product || !$product->is_visible()) {
    return;
}
$label = brandhouse_card_label($product);
$qa = brandhouse_quick_add_data($product);
$context = brandhouse_card_context($product);
$name = $product->get_name();
?>
<li <?php wc_product_class('card', $product); ?>>
  <div class="card-media">
    <?php
    if ($product->get_image_id()) {
        echo $product->get_image('woocommerce_thumbnail', ['loading' => 'lazy', 'sizes' => '(max-width: 760px) 50vw, 25vw', 'alt' => $name]);
        $gallery = $product->get_gallery_image_ids();
        if ($gallery) {
            echo wp_get_attachment_image($gallery[0], 'woocommerce_thumbnail', false, ['class' => 'alt', 'loading' => 'lazy', 'alt' => '']);
        }
    } else {
        echo brandhouse_placeholder(__('Product photo', 'brandhouse'));
    }
    ?>
    <?php if ($qa): ?>
      <button type="button" class="card-qa" data-qa="<?php echo esc_attr((string) wp_json_encode($qa)); ?>" aria-haspopup="<?php echo $qa['sizes'] ? 'dialog' : 'false'; ?>" aria-label="<?php echo esc_attr(sprintf($qa['sizes'] ? __('Quick add %s: choose a size', 'brandhouse') : __('Add %s to bag', 'brandhouse'), $name)); ?>"><span aria-hidden="true">+</span><span class="card-qa-text"><?php echo esc_html($qa['sizes'] ? __('Quick add', 'brandhouse') : __('Add to bag', 'brandhouse')); ?></span></button>
    <?php endif; ?>
    <?php if ($label): ?><span class="tag<?php echo !$product->is_in_stock() ? ' tag-muted' : ''; ?>"><?php echo esc_html($label); ?></span><?php endif; ?>
  </div>
  <div class="card-body">
    <?php if ($context !== ''): ?><p class="card-cat"><?php echo esc_html($context); ?></p><?php endif; ?>
    <h2 class="card-title"><a class="card-link" href="<?php the_permalink(); ?>"><?php echo esc_html($name); ?></a></h2>
    <p class="card-price"><?php echo $product->get_price_html(); // phpcs:ignore -- WooCommerce price markup ?></p>
  </div>
</li>
