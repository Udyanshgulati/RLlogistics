<?php defined('ABSPATH') || exit; get_header(); ?>
<main id="main" class="page container empty-page">
  <p class="eyebrow">404</p>
  <h1 class="page-title"><?php esc_html_e('This page does not exist', 'brandhouse'); ?></h1>
  <p><a class="btn" href="<?php echo esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/')); ?>"><?php esc_html_e('Continue shopping', 'brandhouse'); ?></a></p>
</main>
<?php get_footer();
