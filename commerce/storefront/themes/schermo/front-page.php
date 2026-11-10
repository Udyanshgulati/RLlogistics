<?php
/**
 * Schermo homepage: glass/bento layout. Schermo leads as the brand; its four collections (Core, Atelier, Milan Icons,
 * Glasgow Cosmic) follow. Copy comes from the owner's deck "Schermo · The Art of Dressing"; products, counts and
 * prices are real store data. No invented claims or reviews.
 */

defined('ABSPATH') || exit;
get_header();

$shop = class_exists('WooCommerce') ? wc_get_page_permalink('shop') : home_url('/');
$slides = schermo_hero_slides();
$collections = schermo_collections();
$teams = array_values(array_filter($collections, static fn ($c) => !empty($c['crest'])));
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

    <div class="gx-cards gx-cards-one">
      <div class="gx-card gx-card-mid glass">
        <p class="eyebrow" data-hero-coll><?php echo esc_html($first['collection']); ?></p>
        <h1 class="gx-title" data-hero-name><?php echo esc_html($first['name']); ?></h1>
        <p class="gx-price" data-hero-price><?php echo wp_kses_post($first['price']); ?></p>
        <a class="btn gx-cta" href="<?php echo esc_url($first['cta_url']); ?>" data-hero-link><span data-hero-cta><?php echo esc_html($first['cta']); ?></span><?php echo brandhouse_icon('arrow'); ?></a>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php do_action('brandhouse_home_after_hero'); ?>

  <section class="sb-brand container" aria-labelledby="brand-title">
    <p class="eyebrow"><?php esc_html_e('Schermo · Milano · Est. 2021', 'schermo'); ?></p>
    <h2 id="brand-title" class="sb-display"><?php esc_html_e('A new expression of sport.', 'schermo'); ?> <span><?php esc_html_e('A new language of style.', 'schermo'); ?></span></h2>
    <p class="sb-lede"><?php esc_html_e('A contemporary sports, active and lifestyle label for a generation that sees no boundaries between performance, fashion and everyday expression.', 'schermo'); ?></p>
  </section>

  <?php $types = schermo_home_types(); if ($types): ?>
  <section class="home-section container" aria-labelledby="types-title">
    <div class="section-head"><h2 id="types-title"><?php esc_html_e('Shop by category', 'schermo'); ?></h2><a class="section-link" href="<?php echo esc_url($shop); ?>"><?php esc_html_e('Shop all', 'schermo'); ?><?php echo brandhouse_icon('arrow'); ?></a></div>
    <ul class="sb-types">
      <?php foreach ($types as $t): ?>
        <li><a href="<?php echo esc_url($t['url']); ?>"><span class="sb-type-media"><?php echo $t['image'] ?: brandhouse_placeholder($t['name']); ?></span><span class="sb-type-name"><?php echo esc_html($t['name']); ?></span><span class="sb-type-count"><?php echo esc_html(sprintf(_n('%d product', '%d products', $t['count'], 'schermo'), $t['count'])); ?></span></a></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <?php if ($collections): ?>
  <section class="home-section container" id="collections" aria-labelledby="coll-title">
    <div class="section-head"><h2 id="coll-title"><?php esc_html_e('Four collections. One language.', 'schermo'); ?></h2><a class="section-link" href="<?php echo esc_url($shop); ?>"><?php esc_html_e('View all', 'schermo'); ?><?php echo brandhouse_icon('arrow'); ?></a></div>
    <ul class="sb-colls">
      <?php foreach ($collections as $i => $c): ?>
        <li class="sb-coll sb-<?php echo esc_attr($c['key']); ?>">
          <a href="<?php echo esc_url(get_term_link($c['term'])); ?>">
            <span class="sb-num"><?php echo esc_html(sprintf('%02d', $i + 1)); ?></span>
            <span class="sb-media"><?php echo $c['image'] ?: brandhouse_placeholder($c['term']->name); ?></span>
            <span class="sb-copy">
              <span class="sb-name"><?php echo esc_html($c['label']); ?></span>
              <span class="sb-sub"><?php echo esc_html($c['sub']); ?></span>
              <span class="sb-line"><?php echo esc_html($c['line']); ?></span>
              <span class="sb-state<?php echo $c['ready'] ? ' is-ready' : ''; ?>"><?php echo esc_html($c['ready'] ? sprintf(_n('%d piece available to order', '%d pieces available to order', $c['ready'], 'schermo'), $c['ready']) : __('Coming soon · prices to be announced', 'schermo')); ?></span>
              <span class="sb-count"><?php echo esc_html($c['ready'] ? __('Shop the collection', 'schermo') : sprintf(_n('Preview %d piece', 'Preview %d pieces', (int) $c['term']->count, 'schermo'), (int) $c['term']->count)); ?><?php echo brandhouse_icon('arrow'); ?></span>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <?php
  $ready = function_exists('schermo_available_ids') ? schermo_home_available(12) : [];
  if ($ready):
  ?>
  <section class="home-section container" aria-labelledby="new-title">
    <div class="section-head">
      <h2 id="new-title"><?php esc_html_e('Available to order now', 'schermo'); ?></h2>
      <div class="gx-arrows">
        <button type="button" class="gx-round glass" data-rail-prev aria-label="<?php esc_attr_e('Previous', 'schermo'); ?>"><?php echo brandhouse_icon('arrow'); ?></button>
        <button type="button" class="gx-round glass" data-rail-next aria-label="<?php esc_attr_e('Next', 'schermo'); ?>"><?php echo brandhouse_icon('arrow'); ?></button>
      </div>
    </div>
    <ul class="products gx-rail" data-rail>
      <?php foreach ($ready as $rid) { $GLOBALS['product'] = wc_get_product($rid); $GLOBALS['post'] = get_post($rid); setup_postdata($GLOBALS['post']); wc_get_template_part('content', 'product'); } wp_reset_postdata(); ?>
    </ul>
  </section>
  <?php endif; ?>

  <section class="sb-milan container" aria-labelledby="milan-title">
    <div class="sb-milan-inner">
      <img class="sb-milan-img" src="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/img/banner-milan-icons.webp'); ?>" alt="<?php esc_attr_e('Milan Cathedral (Duomo) at sunset', 'schermo'); ?>" width="1183" height="1400" loading="lazy">
      <div class="sb-milan-copy">
        <p class="eyebrow"><?php esc_html_e('Made in Milan', 'schermo'); ?></p>
        <h2 id="milan-title"><?php esc_html_e('Milano.', 'schermo'); ?></h2>
        <p class="sb-milan-lede"><?php esc_html_e('Italian design sensibility in every piece.', 'schermo'); ?></p>
        <p><?php esc_html_e('A city synonymous with design excellence, craftsmanship and fashion innovation. Every Schermo piece is made here, where form, function, proportion and detailing come together.', 'schermo'); ?></p>
        <p class="sb-milan-founded"><?php esc_html_e('Founded in 2021 by Shazi Ahmad and Dr. Rashid Ali Khan.', 'schermo'); ?></p>
      </div>
    </div>
  </section>

  <section class="home-section container" aria-labelledby="ways-title">
    <div class="section-head"><h2 id="ways-title"><?php esc_html_e('One brand, three ways to move.', 'schermo'); ?></h2></div>
    <ul class="sb-ways">
      <li class="glass"><span class="sb-ghost">01</span><h3><?php esc_html_e('Sports & Performance', 'schermo'); ?></h3><p><?php esc_html_e('Built around movement, functionality and the evolving culture of sport, with comfort, utility and performance aesthetics.', 'schermo'); ?></p></li>
      <li class="glass"><span class="sb-ghost">02</span><h3><?php esc_html_e('Active & Everyday', 'schermo'); ?></h3><p><?php esc_html_e('Pieces that move naturally between activity, travel, leisure and everyday life, without compromising design.', 'schermo'); ?></p></li>
      <li class="glass"><span class="sb-ghost">03</span><h3><?php esc_html_e('Fashion & Lifestyle', 'schermo'); ?></h3><p><?php esc_html_e('A design language shaped by Italian sensibility: contemporary silhouettes, refined detailing, modern culture.', 'schermo'); ?></p></li>
    </ul>
  </section>

  <?php if ($teams): ?>
  <section class="gx-collection container" aria-labelledby="teams-title">
    <div class="sb-teams">
      <div class="sb-teams-copy">
        <p class="eyebrow"><?php esc_html_e('Team merchandise', 'schermo'); ?></p>
        <h2 id="teams-title"><?php esc_html_e('Designed by Schermo, worn by the teams.', 'schermo'); ?></h2>
        <p><?php esc_html_e('Full match, practice, travel and off field ranges for Milan Icons and Glasgow Cosmic.', 'schermo'); ?></p>
      </div>
      <ul class="sb-crests">
        <?php foreach ($teams as $t): ?>
          <li><a href="<?php echo esc_url(get_term_link($t['term'])); ?>"><img src="<?php echo esc_url($t['crest']); ?>" alt="<?php echo esc_attr($t['term']->name); ?>" loading="lazy"><span><?php echo esc_html($t['term']->name); ?><?php echo brandhouse_icon('arrow'); ?></span></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endif; ?>

  <section class="home-section container" aria-labelledby="journey-title">
    <div class="section-head"><h2 id="journey-title"><?php esc_html_e('From Milan to the world.', 'schermo'); ?></h2><p class="section-note"><?php esc_html_e('Our business journey', 'schermo'); ?></p></div>
    <ol class="sb-journey">
      <li><span class="sb-year">2021</span><strong><?php esc_html_e('Founded', 'schermo'); ?></strong><p><?php esc_html_e('By Shazi Ahmad and Dr. Rashid Ali Khan, to build a globally relevant brand where sport, fashion and lifestyle meet.', 'schermo'); ?></p></li>
      <li><span class="sb-year">UK · UAE</span><strong><?php esc_html_e('International foundations', 'schermo'); ?></strong><p><?php esc_html_e('The commercial journey begins through trading operations in the United Kingdom and the United Arab Emirates.', 'schermo'); ?></p></li>
      <li class="is-next"><span class="sb-year">India</span><strong><?php esc_html_e('Next chapter · April 2027', 'schermo'); ?></strong><p><?php esc_html_e('A dedicated digital commerce platform and three physical retail stores, one connected Schermo experience.', 'schermo'); ?></p></li>
    </ol>
  </section>

  <section class="story container" aria-labelledby="story-title">
    <?php echo schermo_svg('schermo-mark', 'story-mark'); ?>
    <div class="story-copy glass">
      <p class="eyebrow"><?php esc_html_e('Our philosophy', 'schermo'); ?></p>
      <h2 id="story-title"><?php echo esc_html(brandhouse_mod('schermo_story_title', __('The art of dressing.', 'schermo'))); ?></h2>
      <?php $text = get_theme_mod('schermo_story_text', ''); ?>
      <?php if ($text !== ''): ?>
        <?php echo wpautop(esc_html($text)); ?>
      <?php else: ?>
        <p class="sb-phil"><span><?php esc_html_e('Sportswear should be', 'schermo'); ?></span> <?php esc_html_e('more than functional.', 'schermo'); ?></p>
        <p class="sb-phil"><span><?php esc_html_e('Lifestyle should be', 'schermo'); ?></span> <?php esc_html_e('more than casual.', 'schermo'); ?></p>
        <p class="sb-phil"><span><?php esc_html_e('Fashion should be', 'schermo'); ?></span> <?php esc_html_e('more than appearance.', 'schermo'); ?></p>
        <p class="gx-soft"><?php esc_html_e('Our purpose: to make everyday dressing an expression of individuality.', 'schermo'); ?></p>
      <?php endif; ?>
    </div>
  </section>
</main>
<?php get_footer(); ?>
