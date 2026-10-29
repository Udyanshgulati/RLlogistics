<?php defined('ABSPATH') || exit; ?>
<footer class="site-footer">
  <?php do_action('brandhouse_footer_art'); ?>
  <div class="foot-grid">
    <div class="foot-brand">
      <?php brandhouse_logo('footer'); ?>
      <p><?php echo esc_html(brandhouse_mod('brandhouse_footer_note', __('Shipped from Delhi, India.', 'brandhouse'))); ?></p>
    </div>
    <?php foreach (['footer-shop' => __('Shop', 'brandhouse'), 'footer-help' => __('Help', 'brandhouse'), 'footer-legal' => __('Policies', 'brandhouse')] as $loc => $title): ?>
      <?php if (has_nav_menu($loc)): ?>
        <nav class="foot-col" aria-label="<?php echo esc_attr($title); ?>"><h2><?php echo esc_html($title); ?></h2><?php wp_nav_menu(['theme_location' => $loc, 'container' => false, 'depth' => 1]); ?></nav>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <div class="foot-base">
    <p>&copy; <?php echo esc_html(gmdate('Y') . ' ' . get_bloginfo('name')); ?></p>
    <?php if (class_exists('WooCommerce')): ?><p><?php printf(esc_html__('Prices in %s', 'brandhouse'), esc_html(get_woocommerce_currency())); ?></p><?php endif; ?>
  </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
