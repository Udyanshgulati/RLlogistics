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
$pieces = array_sum(array_map(static fn ($c) => (int) $c['term']->count, $collections));
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
        <p class="gx-big"><?php echo (int) count($collections); ?><span><?php esc_html_e('collections', 'schermo'); ?></span></p>
        <p class="gx-soft"><?php printf(esc_html__('%d pieces. One language: sport, street and Milan in every piece.', 'schermo'), (int) $pieces); ?></p>
        <a class="gx-link" href="<?php echo esc_url($shop); ?>"><?php esc_html_e('Shop all', 'schermo'); ?><?php echo brandhouse_icon('arrow'); ?></a>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php do_action('brandhouse_home_after_hero'); ?>

  <section class="sb-brand container" aria-labelledby="brand-title">
    <p class="eyebrow"><?php esc_html_e('The brand', 'schermo'); ?></p>
    <h2 id="brand-title" class="sb-display"><?php esc_html_e('A new expression of sport.', 'schermo'); ?> <span><?php esc_html_e('A new language of style.', 'schermo'); ?></span></h2>
    <div class="sb-cols">
      <p><?php esc_html_e('A contemporary sports, active and lifestyle brand for a generation that sees no boundaries between performance, fashion and everyday expression.', 'schermo'); ?></p>
      <p><?php esc_html_e('Born from the belief that what you wear should reflect how you move, think and live, wherever life takes you.', 'schermo'); ?></p>
      <p><?php esc_html_e('Not simply about what you wear. About how you present yourself, how you move through the world and define your identity.', 'schermo'); ?></p>
    </div>
  </section>

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
              <span class="sb-count"><?php echo esc_html(sprintf(_n('%d piece', '%d pieces', (int) $c['term']->count, 'schermo'), (int) $c['term']->count)); ?><?php echo brandhouse_icon('arrow'); ?></span>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <?php
  $new = class_exists('WooCommerce') ? new WP_Query(['post_type' => 'product', 'posts_per_page' => 12, 'post_status' => 'publish', 'orderby' => 'rand', 'tax_query' => [['taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => ['schermo-core', 'schermo-atelier']]]]) : null;
  if ($new && $new->have_posts()):
  ?>
  <section class="home-section container" aria-labelledby="new-title">
    <div class="section-head">
      <h2 id="new-title"><?php esc_html_e('New from Schermo', 'schermo'); ?></h2>
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
    <div class="section-head"><h2 id="journey-title"><?php esc_html_e('From Milan to the world.', 'schermo'); ?></h2></div>
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
