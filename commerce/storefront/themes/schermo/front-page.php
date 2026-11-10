<?php
/**
 * Schermo homepage — one clear journey: hero → shop by category → available to order now → a short brand story →
 * collections. The long brand material (three ways, teams, journey, philosophy) lives on the About page.
 * Products, counts, prices and availability are real store data; brand words come from the owner's deck / tagline.
 */

defined('ABSPATH') || exit;
get_header();

$shop = class_exists('WooCommerce') ? wc_get_page_permalink('shop') : home_url('/');
$slides = schermo_hero_slides(); // orderable pieces first
$lead = $slides[0] ?? null;
$collections = schermo_collections();
$types = schermo_home_types();
$ready = function_exists('schermo_available_ids') ? schermo_home_available(12) : [];
$about = get_page_by_path('about');
$about_url = $about && $about->post_status === 'publish' ? get_permalink($about) : '';
?>
<main id="main" class="home hm">

  <section class="hm-hero container" aria-labelledby="hm-title">
    <div class="hm-hero-copy">
      <p class="hm-kicker"><?php echo esc_html(get_bloginfo('description') ?: __('The art of dressing', 'schermo')); ?></p>
      <h1 id="hm-title" class="hm-title"><?php esc_html_e('Sportswear, everyday pieces and team kits.', 'schermo'); ?></h1>
      <p class="hm-lede"><?php esc_html_e('Tees, trousers, shorts, caps and bags from Schermo Core and Atelier, and team merchandise for Milan Icons and Glasgow Cosmic.', 'schermo'); ?></p>
      <a class="btn hm-cta" href="<?php echo esc_url($shop); ?>"><?php esc_html_e('Shop now', 'schermo'); ?><?php echo brandhouse_icon('arrow'); ?></a>
    </div>
    <?php if ($lead): ?>
      <a class="hm-hero-media" href="<?php echo esc_url($lead['url']); ?>" aria-label="<?php echo esc_attr($lead['name']); ?>">
        <img src="<?php echo esc_url($lead['image']); ?>" alt="<?php echo esc_attr($lead['name']); ?>" width="1200" height="1500" fetchpriority="high">
        <span class="hm-hero-tag"><span><?php echo esc_html($lead['name']); ?></span><span class="hm-hero-price"><?php echo wp_kses_post($lead['ready'] ? $lead['price'] : __('Coming soon', 'schermo')); ?></span></span>
      </a>
    <?php endif; ?>
  </section>

  <?php do_action('brandhouse_home_after_hero'); // launch/drop banner when a date is set ?>

  <?php if ($types): ?>
  <section class="hm-section container" aria-labelledby="types-title">
    <div class="hm-head"><h2 id="types-title"><?php esc_html_e('Shop by category', 'schermo'); ?></h2><a class="hm-link" href="<?php echo esc_url($shop); ?>"><?php esc_html_e('Shop all', 'schermo'); ?></a></div>
    <ul class="hm-types" role="list">
      <?php foreach ($types as $t): ?>
        <li><a href="<?php echo esc_url($t['url']); ?>"><span class="hm-type-media"><?php echo $t['image'] ?: brandhouse_placeholder($t['name']); ?></span><span class="hm-type-name"><?php echo esc_html($t['name']); ?></span><span class="hm-type-count"><?php echo esc_html(sprintf(_n('%d piece', '%d pieces', $t['count'], 'schermo'), $t['count'])); ?></span></a></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <?php if ($ready): ?>
  <section class="hm-section container" aria-labelledby="new-title">
    <div class="hm-head">
      <h2 id="new-title"><?php esc_html_e('Available to order now', 'schermo'); ?></h2>
      <a class="hm-link" href="<?php echo esc_url(add_query_arg('available', '1', $shop)); ?>"><?php esc_html_e('View all', 'schermo'); ?></a>
    </div>
    <ul class="products gx-rail hm-rail" data-rail>
      <?php foreach ($ready as $rid) { $GLOBALS['product'] = wc_get_product($rid); $GLOBALS['post'] = get_post($rid); setup_postdata($GLOBALS['post']); wc_get_template_part('content', 'product'); } wp_reset_postdata(); ?>
    </ul>
  </section>
  <?php endif; ?>

  <section class="hm-story container" aria-labelledby="story-title">
    <img class="hm-story-img" src="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/img/banner-milan-icons.webp'); ?>" alt="<?php esc_attr_e('Milan Cathedral (Duomo) at sunset', 'schermo'); ?>" width="1183" height="1400" loading="lazy">
    <div class="hm-story-copy">
      <p class="hm-kicker"><?php esc_html_e('Milano · Est. 2021', 'schermo'); ?></p>
      <h2 id="story-title"><?php esc_html_e('The art of dressing.', 'schermo'); ?></h2>
      <p><?php esc_html_e('A contemporary sports, active and lifestyle label for a generation that sees no boundaries between performance, fashion and everyday expression.', 'schermo'); ?></p>
      <p class="hm-fine"><?php esc_html_e('Founded in 2021 by Shazi Ahmad and Dr. Rashid Ali Khan.', 'schermo'); ?></p>
      <?php if ($about_url): ?><a class="hm-link" href="<?php echo esc_url($about_url); ?>"><?php esc_html_e('Our story', 'schermo'); ?></a><?php endif; ?>
    </div>
  </section>

  <?php if ($collections): ?>
  <section class="hm-section container" id="collections" aria-labelledby="coll-title">
    <div class="hm-head"><h2 id="coll-title"><?php esc_html_e('Collections', 'schermo'); ?></h2></div>
    <ul class="hm-colls" role="list">
      <?php foreach ($collections as $c): ?>
        <li class="hm-coll hm-<?php echo esc_attr($c['key']); ?>">
          <a href="<?php echo esc_url(get_term_link($c['term'])); ?>">
            <span class="hm-coll-media"><?php echo $c['image'] ?: brandhouse_placeholder($c['term']->name); ?></span>
            <span class="hm-coll-name"><?php echo esc_html($c['term']->name); ?></span>
            <span class="hm-coll-line"><?php echo esc_html($c['sub']); ?></span>
            <span class="hm-state<?php echo $c['ready'] ? ' is-ready' : ''; ?>"><?php echo esc_html($c['ready'] ? sprintf(_n('%d available now', '%d available now', $c['ready'], 'schermo'), $c['ready']) : __('Coming soon', 'schermo')); ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>
</main>
<?php get_footer(); ?>
