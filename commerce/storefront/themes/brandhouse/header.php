<?php defined('ABSPATH') || exit; ?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip" href="#main"><?php esc_html_e('Skip to content', 'brandhouse'); ?></a>
<?php $announce = get_theme_mod('brandhouse_announcement', ''); if ($announce !== ''): ?>
  <div class="announce"><p><?php echo esc_html($announce); ?></p></div>
<?php endif; ?>
<header class="site-header" data-header>
  <div class="bar">
    <button class="icon-btn menu-toggle" type="button" aria-controls="drawer-menu" aria-expanded="false" data-menu-open><?php echo brandhouse_icon('menu'); ?><span class="sr-only"><?php esc_html_e('Menu', 'brandhouse'); ?></span></button>
    <nav class="nav-primary" aria-label="<?php esc_attr_e('Main', 'brandhouse'); ?>">
      <?php wp_nav_menu(['theme_location' => 'primary', 'container' => false, 'depth' => 1, 'fallback_cb' => 'brandhouse_menu_fallback']); ?>
    </nav>
    <div class="brand"><?php brandhouse_logo(); ?></div>
    <div class="tools">
      <button class="icon-btn" type="button" data-search-open aria-controls="site-search"><?php echo brandhouse_icon('search'); ?><span class="sr-only"><?php esc_html_e('Search', 'brandhouse'); ?></span></button>
      <?php if (class_exists('WooCommerce')): ?>
        <a class="icon-btn hide-sm" href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>"><?php echo brandhouse_icon('user'); ?><span class="sr-only"><?php esc_html_e('Account', 'brandhouse'); ?></span></a>
        <a class="icon-btn bag" href="<?php echo esc_url(wc_get_cart_url()); ?>"><?php echo brandhouse_icon('bag'); ?><span class="sr-only"><?php esc_html_e('Bag', 'brandhouse'); ?></span><?php echo brandhouse_bag_count(); ?></a>
      <?php endif; ?>
    </div>
  </div>
  <div class="search-panel" id="site-search" hidden>
    <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
      <label class="sr-only" for="q"><?php esc_html_e('Search products', 'brandhouse'); ?></label>
      <input id="q" type="search" name="s" placeholder="<?php esc_attr_e('Search products', 'brandhouse'); ?>" value="<?php echo esc_attr(get_search_query()); ?>">
      <input type="hidden" name="post_type" value="product">
      <button class="btn"><?php esc_html_e('Search', 'brandhouse'); ?></button>
    </form>
  </div>
</header>
<dialog class="drawer-menu" id="drawer-menu" aria-label="<?php esc_attr_e('Menu', 'brandhouse'); ?>">
  <div class="drawer-head"><?php brandhouse_logo(); ?><button class="icon-btn" type="button" data-dialog-close><?php echo brandhouse_icon('close'); ?><span class="sr-only"><?php esc_html_e('Close menu', 'brandhouse'); ?></span></button></div>
  <nav aria-label="<?php esc_attr_e('Main', 'brandhouse'); ?>"><?php wp_nav_menu(['theme_location' => 'primary', 'container' => false, 'depth' => 1, 'fallback_cb' => 'brandhouse_menu_fallback']); ?></nav>
  <?php if (class_exists('WooCommerce')): ?><a class="drawer-link" href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>"><?php esc_html_e('Account', 'brandhouse'); ?></a><?php endif; ?>
</dialog>
