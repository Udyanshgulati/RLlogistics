<?php
/**
 * Product media. Desktop: one large photo with a row of thumbnails under it; choosing a thumbnail shows that photo
 * (without JavaScript each thumbnail opens the full photo). Phone: all photos become one swipeable row with a
 * counter. Every frame has the same 4:5 shape, so nothing jumps while photos load. Missing photos show placeholders.
 */

defined('ABSPATH') || exit;

global $product;

$main = (int) $product->get_image_id();
$rest = array_values(array_filter(array_map('intval', $product->get_gallery_image_ids()), static fn (int $id): bool => $id !== $main));
$all = $main ? array_merge([$main], $rest) : $rest;
$name = $product->get_name();
$alt = static fn (int $i): string => count($all) > 1 ? sprintf(__('%1$s — view %2$d of %3$d', 'brandhouse'), $name, $i + 1, count($all)) : $name;
?>
<div class="pdp-media images woocommerce-product-gallery<?php echo $rest ? '' : ' is-single'; ?>" data-gallery>
  <div class="pdp-hero woocommerce-product-gallery__image">
    <?php echo $main ? wp_get_attachment_image($main, 'woocommerce_single', false, ['class' => 'pdp-img wp-post-image', 'fetchpriority' => 'high', 'alt' => $alt(0), 'data-gallery-main' => '']) : brandhouse_placeholder(__('Product photo', 'brandhouse')); ?>
  </div>
  <?php if (count($all) > 1): ?>
    <ul class="pdp-thumbs" aria-label="<?php esc_attr_e('Product photos', 'brandhouse'); ?>">
      <?php foreach ($all as $i => $id): $full = wp_get_attachment_image_src($id, 'woocommerce_single'); ?>
        <li><a class="pdp-thumb" href="<?php echo esc_url($full ? $full[0] : ''); ?>" data-gallery-thumb="<?php echo (int) $i; ?>" data-src="<?php echo esc_url($full ? $full[0] : ''); ?>" data-srcset="<?php echo esc_attr((string) wp_get_attachment_image_srcset($id, 'woocommerce_single')); ?>" data-alt="<?php echo esc_attr($alt($i)); ?>"<?php echo $i === 0 ? ' aria-current="true"' : ''; ?>>
          <?php echo wp_get_attachment_image($id, 'woocommerce_thumbnail', false, ['alt' => sprintf(__('Show view %d', 'brandhouse'), $i + 1), 'loading' => 'lazy']); ?>
        </a></li>
      <?php endforeach; ?>
    </ul>
    <div class="pdp-swipe" data-swipe aria-label="<?php esc_attr_e('Product photos', 'brandhouse'); ?>">
      <?php foreach ($all as $i => $id): ?><figure class="pdp-shot"><?php echo wp_get_attachment_image($id, 'woocommerce_single', false, ['class' => 'pdp-img', 'alt' => $alt($i), 'loading' => $i === 0 ? 'eager' : 'lazy']); ?></figure><?php endforeach; ?>
    </div>
    <p class="pdp-count" aria-hidden="true"><span data-swipe-index>1</span> / <?php echo count($all); ?></p>
  <?php endif; ?>
</div>
