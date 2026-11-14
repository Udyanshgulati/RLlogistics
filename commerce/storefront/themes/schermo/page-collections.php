<?php
/**
 * Collections page (/collections/): the three Schermo collections (Schermo Sports = Milan Icons + Glasgow Cosmic), each with its own image, line from the owner's deck,
 * real piece count and real availability. "Shop" only for collections with pieces that can be ordered now.
 */

defined('ABSPATH') || exit;
get_header();
$collections = function_exists('schermo_collections') ? schermo_collections() : [];
$img = get_stylesheet_directory_uri() . '/assets/img/';
$banner = ['schermo-sports' => $img . 'banner-milan-icons.webp'];
$teams = function_exists('schermo_teams') ? schermo_teams() : [];
$line = [
    'schermo-core' => __('The everyday Schermo line. Oversized tees, baggy trousers and caps with sport details: stripes, panels, numbers and stadium graphics.', 'schermo'),
    'schermo-atelier' => __('Limited pieces, made to be noticed. Hand stitched contours, embroidered florals, patchwork and raw edges.', 'schermo'),
    'schermo-sports' => __('Team merchandise designed by Schermo for Milan Icons and Glasgow Cosmic: match, practice, winter, travel and accessories.', 'schermo'),
];
?>
<main id="main" class="cl-page container">
  <nav class="crumbs" aria-label="Breadcrumb"><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'schermo'); ?></a><span class="sep" aria-hidden="true">/</span><?php esc_html_e('Collections', 'schermo'); ?></nav>
  <header class="cl-head">
    <h1><?php esc_html_e('Collections', 'schermo'); ?></h1>
    <p><?php esc_html_e('Three collections. One language. Each Schermo line has its own story; every piece shares the same design sensibility.', 'schermo'); ?></p>
  </header>
  <ul class="cl-grid">
    <?php foreach ($collections as $i => $c): $url = get_term_link($c['term']); $count = (int) $c['term']->count; ?>
      <li class="cl-card cl-<?php echo esc_attr($c['key']); ?>">
        <a class="cl-media" href="<?php echo esc_url($url); ?>" tabindex="-1" aria-hidden="true">
          <?php if (isset($banner[$c['slug']])): ?>
            <img src="<?php echo esc_url($banner[$c['slug']]); ?>" alt="" width="1183" height="1400" loading="<?php echo $i < 2 ? 'eager' : 'lazy'; ?>" class="is-photo">
          <?php else: ?>
            <?php echo $c['image'] ?: brandhouse_placeholder($c['term']->name); ?>
          <?php endif; ?>
          <span class="cl-num"><?php echo esc_html(sprintf('%02d', $i + 1)); ?></span>
        </a>
        <div class="cl-body">
          <p class="cl-sub"><?php echo esc_html($c['sub']); ?></p>
          <h2><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($c['term']->name); ?></a></h2>
          <p class="cl-line"><?php echo esc_html($line[$c['slug']] ?? $c['line']); ?></p>
          <?php if ($c['slug'] === 'schermo-sports' && $teams): ?>
            <ul class="cl-teams" aria-label="<?php esc_attr_e('Teams', 'schermo'); ?>">
              <?php foreach ($teams as $t): ?><li><a href="<?php echo esc_url(get_term_link($t['term'])); ?>"><img src="<?php echo esc_url($t['crest']); ?>" alt="" width="28" height="28" loading="lazy"><?php echo esc_html($t['term']->name); ?></a></li><?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <p class="cl-meta">
            <span><?php echo esc_html(sprintf(_n('%d piece', '%d pieces', $count, 'schermo'), $count)); ?></span>
            <span class="sb-state<?php echo $c['ready'] ? ' is-ready' : ''; ?>"><?php echo esc_html($c['ready'] ? sprintf(_n('%d available to order', '%d available to order', $c['ready'], 'schermo'), $c['ready']) : __('Coming soon · prices to be announced', 'schermo')); ?></span>
          </p>
          <a class="btn<?php echo $c['ready'] ? '' : ' btn-outline'; ?>" href="<?php echo esc_url($url); ?>"><?php echo esc_html(sprintf($c['ready'] ? __('Shop %s', 'schermo') : __('Preview %s', 'schermo'), $c['term']->name)); ?><?php echo brandhouse_icon('arrow'); ?></a>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
</main>
<?php get_footer(); ?>
