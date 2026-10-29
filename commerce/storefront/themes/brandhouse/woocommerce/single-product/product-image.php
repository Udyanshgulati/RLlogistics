<?php
/**
 * Product media. Desktop: the main photo stays in view on the left while the remaining photos scroll beside it.
 * Phone: all photos become one swipeable row with a counter. Missing photos show labelled placeholders.
 */

defined('ABSPATH') || exit;

global $product;

$main = (int) $product->get_image_id();
$rest = array_values(array_filter(array_map('intval', $product->get_gallery_image_ids()), static fn (int $id): bool => $id !== $main));
$all = $main ? array_merge([$main], $rest) : $rest;
$img = static fn (int $id, string $class = ''): string => wp_get_attachment_image($id, 'woocommerce_single', false, ['class' => trim('pdp-img ' . $class), 'loading' => 'lazy']);
?>
<div class="pdp-media images woocommerce-product-gallery<?php echo $rest ? '' : ' is-single'; ?>">
  <div class="pdp-hero woocommerce-product-gallery__image">
    <?php echo $main ? wp_get_attachment_image($main, 'woocommerce_single', false, ['class' => 'pdp-img wp-post-image', 'fetchpriority' => 'high']) : brandhouse_placeholder(__('Product photo', 'brandhouse')); ?>
  </div>
  <?php if ($rest): ?>
    <div class="pdp-stack" aria-label="<?php esc_attr_e('More photos', 'brandhouse'); ?>">
      <?php foreach ($rest as $id): ?><figure class="pdp-shot"><?php echo $img($id); ?></figure><?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if (count($all) > 1): ?>
    <div class="pdp-swipe" data-swipe aria-label="<?php esc_attr_e('Product photos', 'brandhouse'); ?>">
      <?php foreach ($all as $id): ?><figure class="pdp-shot"><?php echo $img($id); ?></figure><?php endforeach; ?>
    </div>
    <p class="pdp-count" aria-hidden="true"><span data-swipe-index>1</span> / <?php echo count($all); ?></p>
  <?php endif; ?>
</div>
