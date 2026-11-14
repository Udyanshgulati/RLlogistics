<?php
/**
 * About Schermo (page slug "about"): the brand material that used to fill the homepage — what Schermo is, the three
 * ways it is worn, the team collections, the business journey and the philosophy. Words from the owner's deck.
 */

defined('ABSPATH') || exit;
get_header();
$teams = function_exists('schermo_teams') ? schermo_teams() : [];
$shop = class_exists('WooCommerce') ? wc_get_page_permalink('shop') : home_url('/');
?>
<main id="main" class="ab container">
  <header class="ab-head">
    <p class="hm-kicker"><?php esc_html_e('Milano · Est. 2021', 'schermo'); ?></p>
    <h1 class="ab-title"><?php the_title(); ?></h1>
    <p class="ab-lede"><?php esc_html_e('A contemporary sports, active and lifestyle label for a generation that sees no boundaries between performance, fashion and everyday expression.', 'schermo'); ?></p>
    <p class="hm-fine"><?php esc_html_e('Founded in 2021 by Shazi Ahmad and Dr. Rashid Ali Khan, to build a globally relevant brand where sport, fashion and lifestyle meet.', 'schermo'); ?></p>
  </header>

  <?php while (have_posts()): the_post(); if (trim((string) get_the_content()) !== ''): ?>
    <div class="ab-content"><?php the_content(); ?></div>
  <?php endif; endwhile; ?>

  <section class="ab-section" aria-labelledby="ways-title">
    <h2 id="ways-title"><?php esc_html_e('One brand, three ways to wear it', 'schermo'); ?></h2>
    <ul class="ab-ways" role="list">
      <li><h3><?php esc_html_e('Sports & Performance', 'schermo'); ?></h3><p><?php esc_html_e('Built around movement, functionality and the evolving culture of sport, with comfort, utility and performance aesthetics.', 'schermo'); ?></p></li>
      <li><h3><?php esc_html_e('Active & Everyday', 'schermo'); ?></h3><p><?php esc_html_e('Pieces that move naturally between activity, travel, leisure and everyday life, without compromising design.', 'schermo'); ?></p></li>
      <li><h3><?php esc_html_e('Fashion & Lifestyle', 'schermo'); ?></h3><p><?php esc_html_e('A design language shaped by Italian sensibility: contemporary silhouettes, refined detailing, modern culture.', 'schermo'); ?></p></li>
    </ul>
  </section>

  <?php if ($teams): ?>
  <section class="ab-section" aria-labelledby="teams-title">
    <h2 id="teams-title"><?php esc_html_e('Schermo Sports', 'schermo'); ?></h2>
    <p><?php esc_html_e('Team merchandise designed by Schermo: match, practice, travel and off-field ranges for Milan Icons and Glasgow Cosmic.', 'schermo'); ?></p>
    <ul class="ab-crests" role="list">
      <?php foreach ($teams as $t): ?>
        <li><a href="<?php echo esc_url(get_term_link($t['term'])); ?>"><img src="<?php echo esc_url($t['crest']); ?>" alt="" width="64" height="64" loading="lazy"><span><?php echo esc_html($t['term']->name); ?></span></a></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <section class="ab-section" aria-labelledby="journey-title">
    <h2 id="journey-title"><?php esc_html_e('Our business journey', 'schermo'); ?></h2>
    <ol class="ab-journey">
      <li><span class="ab-year">2021</span><div><strong><?php esc_html_e('Founded', 'schermo'); ?></strong><p><?php esc_html_e('By Shazi Ahmad and Dr. Rashid Ali Khan.', 'schermo'); ?></p></div></li>
      <li><span class="ab-year">UK · UAE</span><div><strong><?php esc_html_e('International foundations', 'schermo'); ?></strong><p><?php esc_html_e('The commercial journey begins through trading operations in the United Kingdom and the United Arab Emirates.', 'schermo'); ?></p></div></li>
      <li><span class="ab-year"><?php esc_html_e('Next', 'schermo'); ?></span><div><strong><?php esc_html_e('April 2027', 'schermo'); ?></strong><p><?php esc_html_e('A dedicated digital commerce platform and three physical retail stores, one connected Schermo experience.', 'schermo'); ?></p></div></li>
    </ol>
  </section>

  <section class="ab-section ab-phil" aria-labelledby="phil-title">
    <h2 id="phil-title"><?php esc_html_e('Our philosophy', 'schermo'); ?></h2>
    <p><?php esc_html_e('Sportswear should be more than functional. Lifestyle should be more than casual. Fashion should be more than appearance.', 'schermo'); ?></p>
    <p><?php esc_html_e('Our purpose: to make everyday dressing an expression of individuality.', 'schermo'); ?></p>
    <p><a class="btn" href="<?php echo esc_url($shop); ?>"><?php esc_html_e('Shop now', 'schermo'); ?><?php echo brandhouse_icon('arrow'); ?></a></p>
  </section>
</main>
<?php get_footer(); ?>
