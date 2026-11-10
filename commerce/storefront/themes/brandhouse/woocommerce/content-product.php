<?php
/**
 * Product card: image (second view on hover only when the product has one), a quiet collection/type line, name,
 * real price (or "Coming soon"), sizes only when the piece can be ordered, and at most one honest label.
 * The whole card is one link (the title's link is stretched over the card), so it is a single tab stop.
 */
defined('ABSPATH') || exit;

global $product;
if (!$product instanceof WC_Product || !$product->is_visible()) {
    return;
}
$label = brandhouse_card_label($product);
$sizes = $product->is_purchasable() ? brandhouse_card_sizes($product) : [];
$context = brandhouse_card_context($product);
$name = $product->get_name();
?>
<li <?php wc_product_class('card', $product); ?>>
  <div class="card-media" aria-hidden="true">
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
    <?php if ($label): ?><span class="tag<?php echo !$product->is_in_stock() ? ' tag-muted' : ''; ?>"><?php echo esc_html($label); ?></span><?php endif; ?>
  </div>
  <div class="card-body">
    <?php if ($context !== ''): ?><p class="card-cat"><?php echo esc_html($context); ?></p><?php endif; ?>
    <h2 class="card-title"><a class="card-link" href="<?php the_permalink(); ?>"><?php echo esc_html($name); ?></a></h2>
    <p class="card-price"><?php echo $product->get_price_html(); // phpcs:ignore -- WooCommerce price markup ?></p>
    <?php if ($sizes): ?>
      <p class="card-sizes"><span class="sr-only"><?php esc_html_e('Sizes:', 'brandhouse'); ?></span>
        <?php foreach ($sizes as $s): ?><span class="<?php echo $s['in'] ? '' : 'out'; ?>"><?php echo esc_html($s['label']); ?><?php if (!$s['in']): ?><span class="sr-only"> <?php esc_html_e('(sold out)', 'brandhouse'); ?></span><?php endif; ?></span><?php endforeach; ?>
      </p>
    <?php endif; ?>
  </div>
</li>
