<?php
/**
 * Schermo homepage: glass/bento layout. Hero products, labels and words come from schermo_hero_slides();
 * everything else is real store data (categories, newest products, counts). No invented claims or reviews.
 */

defined('ABSPATH') || exit;
get_header();

$shop = class_exists('WooCommerce') ? wc_get_page_permalink('shop') : home_url('/');
$slides = schermo_hero_slides();
$cats = class_exists('WooCommerce') ? get_terms(['taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => true, 'exclude' => [(int) get_option('default_product_cat')]]) : [];
$collection = get_term_by('slug', 'milan-icons', 'product_cat');
?>
<main id="main" class="home home-glass">

  <?php if ($slides): $first = $slides[0]; ?>
  <section class="gx-hero" aria-roledescription="carousel" aria-label="<?php esc_attr_e('Featured products', 'schermo'); ?>" data-hero>
    <?php echo schermo_svg('schermo-mark', 'gx-mark'); ?>
    <ol class="gx-index" aria-label="<?php esc_attr_e('Choose a product', 'schermo'); ?>">
      <?php foreach ($slides as $i => $s): ?>
        <li><button type="button" data-slide="<?php echo (int) $i; ?>" aria-current="<?php echo $i === 0 ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr($s['name']); ?>"><?php echo esc_html(sprintf('%02d', $i + 1)); ?></button></li>
      <?php endforeach; ?>
    </ol>

    <?php foreach ($slides as $i => $s): ?>
      <div class="gx-slide<?php echo $i === 0 ? ' is-active' : ''; ?>" data-index="<?php echo (int) $i; ?>"<?php echo $i === 0 ? '' : ' aria-hidden="true"'; ?>>
        <p class="gx-word gx-word-a" aria-hidden="true"><?php echo esc_html($s['words'][0]); ?></p>
        <p class="gx-word gx-word-b" aria-hidden="true"><?php echo esc_html($s['words'][1]); ?></p>
        <figure class="gx-product">
          <img src="<?php echo esc_url($s['image']); ?>" alt="<?php echo esc_attr($s['name']); ?>" <?php echo $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"'; ?>>
          <?php foreach ($s['spots'] as $k => [$label, $x, $y, $side]): ?>
            <span class="gx-spot gx-<?php echo esc_attr($side); ?>" style="--x:<?php echo (float) $x; ?>%;--y:<?php echo (float) $y; ?>%;--d:<?php echo (int) $k; ?>">
              <span class="gx-dot"></span><span class="gx-line"></span><span class="gx-tag glass"><?php echo esc_html($label); ?></span>
            </span>
          <?php endforeach; ?>
        </figure>
      </div>
    <?php endforeach; ?>

    <div class="gx-cards">
      <div class="gx-card glass">
        <div class="gx-icons"><?php echo brandhouse_icon('truck') . brandhouse_icon('lock') . brandhouse_icon('return'); ?></div>
        <p class="gx-strong"><?php esc_html_e('Ships from Delhi, India', 'schermo'); ?></p>
        <p class="gx-soft"><?php esc_html_e('Delivery options and estimates are shown at checkout. Card details are handled by the payment provider.', 'schermo'); ?></p>
      </div>
      <div class="gx-card gx-card-mid glass">
        <a class="btn gx-cta" href="<?php echo esc_url($first['url']); ?>" data-hero-link><?php esc_html_e('Shop now', 'schermo'); ?><?php echo brandhouse_icon('arrow'); ?></a>
        <p class="eyebrow" data-hero-coll><?php echo esc_html($first['collection']); ?></p>
        <h1 class="gx-title" data-hero-name><?php echo esc_html($first['name']); ?></h1>
        <p class="gx-price" data-hero-price><?php echo wp_kses_post($first['price']); ?></p>
      </div>
      <div class="gx-card glass">
        <?php if ($collection && !is_wp_error($collection)): ?>
          <p class="gx-big"><?php echo (int) $collection->count; ?><span><?php esc_html_e('pieces', 'schermo'); ?></span></p>
          <p class="gx-soft"><?php printf(esc_html__('The %s collection — kits, travel wear and accessories.', 'schermo'), esc_html($collection->name)); ?></p>
          <a class="gx-link" href="<?php echo esc_url(get_term_link($collection)); ?>"><?php esc_html_e('Explore the collection', 'schermo'); ?><?php echo brandhouse_icon('arrow'); ?></a>
        <?php else: ?>
          <p class="gx-strong"><?php esc_html_e('New pieces added regularly', 'schermo'); ?></p>
          <a class="gx-link" href="<?php echo esc_url($shop); ?>"><?php esc_html_e('Shop all', 'schermo'); ?><?php echo brandhouse_icon('arrow'); ?></a>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php do_action('brandhouse_home_after_hero'); ?>

  <?php if ($cats && !is_wp_error($cats)): ?>
  <section class="home-section container" aria-labelledby="cats-title">
    <div class="section-head"><h2 id="cats-title"><?php esc_html_e('Shop by category', 'schermo'); ?></h2><a class="section-link" href="<?php echo esc_url($shop); ?>"><?php esc_html_e('View all', 'schermo'); ?><?php echo brandhouse_icon('arrow'); ?></a></div>
    <ul class="gx-bento">
      <?php foreach ($cats as $i => $cat): $img = schermo_category_image($cat); ?>
        <li class="gx-tile<?php echo $i === 0 ? ' is-wide' : ''; ?>">
          <a href="<?php echo esc_url(get_term_link($cat)); ?>">
            <?php echo $img ?: brandhouse_placeholder(__('Category image', 'schermo')); ?>
            <span class="gx-tile-name"><span class="gx-tile-count"><?php echo esc_html(sprintf(_n('%d piece', '%d pieces', (int) $cat->count, 'schermo'), (int) $cat->count)); ?></span><?php echo esc_html($cat->name); ?></span>
            <span class="gx-notch" aria-hidden="true"><?php echo brandhouse_icon('arrow'); ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <?php
  $new = class_exists('WooCommerce') ? new WP_Query(['post_type' => 'product', 'posts_per_page' => 12, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC']) : null;
  if ($new && $new->have_posts()):
  ?>
  <section class="home-section container" aria-labelledby="new-title">
    <div class="section-head">
      <h2 id="new-title"><?php esc_html_e('New arrivals', 'schermo'); ?></h2>
      <div class="gx-arrows">
        <button type="button" class="gx-round glass" data-rail-prev aria-label="<?php esc_attr_e('Previous', 'schermo'); ?>"><?php echo brandhouse_icon('arrow'); ?></button>
        <button type="button" class="gx-round glass" data-rail-next aria-label="<?php esc_attr_e('Next', 'schermo'); ?>"><?php echo brandhouse_icon('arrow'); ?></button>
      </div>
    </div>
    <ul class="products gx-rail" data-rail>
      <?php while ($new->have_posts()): $new->the_post(); wc_get_template_part('content', 'product'); endwhile; wp_reset_postdata(); ?>
    </ul>
  </section>
  <?php endif; ?>

  <?php if ($collection && !is_wp_error($collection)): ?>
  <section class="gx-collection container" aria-labelledby="coll-title">
    <div class="gx-coll-inner">
      <img class="gx-crest" src="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/img/milan-icons-crest.jpg'); ?>" alt="<?php echo esc_attr($collection->name); ?>" loading="lazy">
      <div class="gx-coll-copy">
        <p class="eyebrow"><?php esc_html_e('Collection', 'schermo'); ?></p>
        <h2 id="coll-title"><?php echo esc_html($collection->name); ?></h2>
        <p class="gx-coll-line">Play Beyond · Sempre Avanti</p>
        <a class="btn gx-gold" href="<?php echo esc_url(get_term_link($collection)); ?>"><?php esc_html_e('Shop the collection', 'schermo'); ?><?php echo brandhouse_icon('arrow'); ?></a>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <section class="story container" aria-labelledby="story-title">
    <?php echo schermo_svg('schermo-mark', 'story-mark'); ?>
    <div class="story-copy glass">
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
</main>
<?php get_footer(); ?>
