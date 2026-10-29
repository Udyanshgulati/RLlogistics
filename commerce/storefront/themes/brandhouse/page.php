<?php defined('ABSPATH') || exit; get_header(); ?>
<main id="main" class="page container">
  <?php if (have_posts()): while (have_posts()): the_post(); ?>
    <article <?php post_class('entry'); ?>>
      <h1 class="page-title"><?php the_title(); ?></h1>
      <div class="prose"><?php the_content(); ?></div>
    </article>
  <?php endwhile; else: ?>
    <h1 class="page-title"><?php esc_html_e('Nothing here yet', 'brandhouse'); ?></h1>
  <?php endif; ?>
</main>
<?php get_footer();
