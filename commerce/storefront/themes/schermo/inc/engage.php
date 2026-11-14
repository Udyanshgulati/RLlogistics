<?php
/**
 * Visitor journey prompts, one at a time and never on cart, checkout or account pages:
 *  1. ~6 s after a first visit: currency follows the visitor's location (a small, non-blocking note; a one-click
 *     switch when the location suggests a different market). Replaces the old full-screen picker on arrival.
 *  2. ~15 s in (once per visitor): a short welcome with the way into the collections.
 *  3. After real interest (2 product pages, Quick add, or reading most of a page for 30 s+), and not within 45 s of
 *     the welcome: the Schermo list sign-up with the welcome offer (percentage from WooCommerce → Schermo messages).
 *     Consent is an unticked box; the code is personal, single use and expires (see schermo-comms newsletter.php).
 * Dismissals are remembered in the browser (14 days for the sign-up); nothing is shown again after signing up.
 */

defined('ABSPATH') || exit;

function schermo_engage_on(): bool
{
    return !is_admin() && function_exists('is_cart') && !is_cart() && !is_checkout() && !is_account_page() && !isset($_GET['review']); // phpcs:ignore WordPress.Security.NonceVerification
}

add_filter('body_class', static fn (array $c): array => schermo_engage_on() ? array_merge($c, ['has-engage']) : $c);

add_action('wp_enqueue_scripts', static function (): void {
    if (!schermo_engage_on()) {
        return;
    }
    $offer = function_exists('scm_offer') ? scm_offer() : ['on' => false, 'percent' => 0, 'days' => 0];
    $milan = get_term_by('slug', 'milan-icons', 'product_cat');
    wp_enqueue_script('schermo-engage', get_stylesheet_directory_uri() . '/assets/js/engage.js', [], brandhouse_asset_ver('/assets/js/engage.js', true), ['strategy' => 'defer', 'in_footer' => true]);
    wp_localize_script('schermo-engage', 'schermoEngage', [
        'detect' => rest_url('brandhouse/v1/market'),
        'newsletter' => function_exists('scm_offer') ? rest_url('scm/v1/newsletter') : '',
        'offer' => $offer['on'] ? (int) $offer['percent'] : 0,
        'isProduct' => is_product(),
    ]);
}, 40);

add_action('wp_footer', static function (): void {
    if (!schermo_engage_on()) {
        return;
    }
    $offer = function_exists('scm_offer') ? scm_offer() : ['on' => false, 'percent' => 0, 'days' => 30];
    $collections = get_page_by_path('collections') ? get_permalink(get_page_by_path('collections')) : wc_get_page_permalink('shop');
    $ready = function_exists('schermo_collections') ? array_values(array_filter(schermo_collections(), static fn ($c) => $c['ready'])) : [];
    ?>
    <div class="eg-toast" data-eg-market role="status" aria-live="polite" hidden>
      <p class="eg-toast-text" data-eg-market-text></p>
      <div class="eg-toast-actions">
        <button type="button" class="btn eg-sm" data-eg-market-switch hidden></button>
        <button type="button" class="link-btn eg-sm" data-eg-market-keep></button>
        <button type="button" class="icon-btn eg-x" data-eg-market-close aria-label="<?php esc_attr_e('Close', 'schermo'); ?>"><?php echo brandhouse_icon('close'); ?></button>
      </div>
    </div>

    <dialog class="eg-dialog eg-welcome" id="eg-welcome" aria-labelledby="eg-welcome-title">
      <button type="button" class="icon-btn eg-x" data-dialog-close aria-label="<?php esc_attr_e('Close', 'schermo'); ?>"><?php echo brandhouse_icon('close'); ?></button>
      <div class="eg-art" aria-hidden="true"><img src="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/img/banner-milan-icons.webp'); ?>" alt="" width="1183" height="1400" loading="lazy"></div>
      <div class="eg-body">
        <p class="eg-kicker"><?php esc_html_e('Benvenuto', 'schermo'); ?></p>
        <h2 id="eg-welcome-title"><?php esc_html_e('Welcome to Schermo.', 'schermo'); ?></h2>
        <p><?php esc_html_e('A new expression of sport. A new language of style. Three collections, one language.', 'schermo'); ?></p>
        <div class="eg-actions">
          <?php if ($ready): $t = $ready[0]['term']; ?>
            <a class="btn" href="<?php echo esc_url(get_term_link($t)); ?>"><?php echo esc_html(sprintf(__('Shop %s', 'schermo'), $t->name)); ?></a>
          <?php endif; ?>
          <a class="btn btn-outline" href="<?php echo esc_url($collections); ?>"><?php esc_html_e('Explore the collections', 'schermo'); ?></a>
        </div>
      </div>
    </dialog>

    <?php if (function_exists('scm_offer')): ?>
    <dialog class="eg-dialog eg-list" id="eg-list" aria-labelledby="eg-list-title">
      <button type="button" class="icon-btn eg-x" data-dialog-close aria-label="<?php esc_attr_e('Close', 'schermo'); ?>"><?php echo brandhouse_icon('close'); ?></button>
      <div class="eg-body">
        <p class="eg-kicker"><?php esc_html_e('The Schermo list', 'schermo'); ?></p>
        <h2 id="eg-list-title"><?php echo esc_html($offer['on'] ? sprintf(__('%d%% off your first order', 'schermo'), $offer['percent']) : __('First to know', 'schermo')); ?></h2>
        <p><?php esc_html_e('New drops, limited Atelier pieces and early access — a few emails, never noise.', 'schermo'); ?></p>
        <form class="eg-form" data-eg-form novalidate>
          <label class="eg-field"><span><?php esc_html_e('Email', 'schermo'); ?></span><input type="email" name="email" autocomplete="email" inputmode="email" required></label>
          <label class="eg-consent"><input type="checkbox" name="consent" value="1" required> <span><?php esc_html_e('Email me about new drops and offers from Schermo. I can unsubscribe at any time.', 'schermo'); ?></span></label>
          <p class="eg-error" role="alert" data-eg-error></p>
          <button type="submit" class="btn"><?php echo esc_html($offer['on'] ? __('Get my code', 'schermo') : __('Join the list', 'schermo')); ?></button>
          <?php if ($offer['on']): ?><p class="eg-fine"><?php echo esc_html(sprintf(__('One personal code for your first order, valid %d days, not on items already on sale.', 'schermo'), $offer['days'])); ?></p><?php endif; ?>
        </form>
        <div class="eg-done" data-eg-done hidden>
          <p class="eg-code-label"><?php esc_html_e('Your code', 'schermo'); ?></p>
          <p class="eg-code"><span data-eg-code></span> <button type="button" class="link-btn" data-eg-copy><?php esc_html_e('Copy', 'schermo'); ?></button></p>
          <p class="eg-fine"><?php esc_html_e('Use it at checkout under “Add coupons”. You are on the list — thank you.', 'schermo'); ?></p>
        </div>
      </div>
    </dialog>
    <?php endif;
}, 25);
