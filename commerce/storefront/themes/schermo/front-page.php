<?php
/**
 * Schermo homepage. Copy and imagery are client content: empty Customizer fields show labelled placeholders.
 */

defined('ABSPATH') || exit;
get_header();

$hero = schermo_image('schermo_hero_image');
$story = schermo_image('schermo_story_image');
$shop = class_exists('WooCommerce') ? wc_get_page_permalink('shop') : home_url('/');
$cats = class_exists('WooCommerce') ? get_terms(['taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => true, 'exclude' => [(int) get_option('default_product_cat')]]) : [];
?>
<main id="main" class="home">
  <section class="hero" aria-labelledby="hero-title">
    <div class="hero-media">
      <?php if ($hero): ?>
        <img src="<?php echo esc_url($hero); ?>" alt="" fetchpriority="high">
      <?php else: ?>
        <?php echo brandhouse_placeholder(__('Campaign image — to be supplied', 'schermo')); ?>
      <?php endif; ?>
    </div>
    <div class="hero-copy container">
      <?php echo schermo_svg('schermo-mark', 'hero-mark'); ?>
      <h1 id="hero-title"><?php echo esc_html(brandhouse_mod('schermo_hero_title', get_bloginfo('name'))); ?></h1>
      <p><?php echo esc_html(brandhouse_mod('schermo_hero_text', __('Clothing · Sportswear · Fan accessories', 'schermo'))); ?></p>
      <a class="btn btn-light" href="<?php echo esc_url($shop); ?>"><?php echo esc_html(brandhouse_mod('schermo_hero_cta', __('Shop all', 'schermo'))); ?><?php echo brandhouse_icon('arrow'); ?></a>
    </div>
  </section>

  <?php do_action('brandhouse_home_after_hero'); ?>

  <?php if ($cats && !is_wp_error($cats)): ?>
  <section class="home-section container" aria-labelledby="cats-title">
    <div class="section-head"><h2 id="cats-title"><?php esc_html_e('Shop by category', 'schermo'); ?></h2></div>
    <ul class="cat-tiles">
      <?php foreach ($cats as $i => $cat): $thumb = (int) get_term_meta($cat->term_id, 'thumbnail_id', true); ?>
        <li class="cat-tile">
          <a href="<?php echo esc_url(get_term_link($cat)); ?>">
            <?php echo $thumb ? wp_get_attachment_image($thumb, 'woocommerce_single') : brandhouse_placeholder(__('Category image', 'schermo')); ?>
            <span class="cat-name"><span class="cat-index"><?php echo esc_html(sprintf('%02d', $i + 1)); ?></span><?php echo esc_html($cat->name); ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <?php
  $new = class_exists('WooCommerce') ? new WP_Query(['post_type' => 'product', 'posts_per_page' => 8, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC']) : null;
  if ($new && $new->have_posts()):
  ?>
  <section class="home-section container" aria-labelledby="new-title">
    <div class="section-head">
      <h2 id="new-title"><?php esc_html_e('New in', 'schermo'); ?></h2>
      <a class="section-link" href="<?php echo esc_url($shop); ?>"><?php esc_html_e('View all', 'schermo'); ?><?php echo brandhouse_icon('arrow'); ?></a>
    </div>
    <ul class="products">
      <?php while ($new->have_posts()): $new->the_post(); wc_get_template_part('content', 'product'); endwhile; wp_reset_postdata(); ?>
    </ul>
  </section>
  <?php endif; ?>

  <section class="story container" aria-labelledby="story-title">
    <?php echo schermo_svg('schermo-mark', 'story-mark'); ?>
    <div class="story-media">
      <?php echo $story ? '<img src="' . esc_url($story) . '" alt="" loading="lazy">' : brandhouse_placeholder(__('Brand image — to be supplied', 'schermo')); ?>
    </div>
    <div class="story-copy">
      <p class="eyebrow"><?php esc_html_e('About', 'schermo'); ?></p>
      <h2 id="story-title"><?php echo esc_html(brandhouse_mod('schermo_story_title', get_bloginfo('name'))); ?></h2>
      <?php $text = get_theme_mod('schermo_story_text', ''); ?>
      <?php if ($text !== ''): ?>
        <?php echo wpautop(esc_html($text)); ?>
      <?php else: ?>
        <p class="placeholder-note"><?php esc_html_e('Brand story to be supplied. Add it in Customize → Homepage.', 'schermo'); ?></p>
      <?php endif; ?>
    </div>
  </section>

  <section class="service container" aria-label="<?php esc_attr_e('Service', 'schermo'); ?>">
    <div><?php echo brandhouse_icon('truck'); ?><p><strong><?php esc_html_e('Ships from Delhi', 'schermo'); ?></strong><?php esc_html_e('Delivery options and estimates shown at checkout', 'schermo'); ?></p></div>
    <div><?php echo brandhouse_icon('return'); ?><p><strong><?php esc_html_e('Returns', 'schermo'); ?></strong><?php echo esc_html(brandhouse_mod('brandhouse_returns_text', __('Policy to be confirmed', 'schermo'))); ?></p></div>
    <div><?php echo brandhouse_icon('lock'); ?><p><strong><?php esc_html_e('Secure checkout', 'schermo'); ?></strong><?php esc_html_e('Card details are handled by the payment provider', 'schermo'); ?></p></div>
  </section>
</main>
<?php get_footer(); ?>
