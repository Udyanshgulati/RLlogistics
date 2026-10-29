<?php
/**
 * Shopping extras: back-in-stock alerts, free-shipping progress in the cart, and a drop countdown.
 * Every figure comes from store settings (stock, shipping method, Customizer); nothing is shown until it is set.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

if (!class_exists('WooCommerce')) {
    return;
}

/* ── Back-in-stock alerts ─────────────────────────────────────────────────────────────────────── */

add_action('init', static function (): void {
    register_post_type('bh_stock_alert', [
        'labels' => ['name' => __('Stock alerts', 'brandhouse'), 'singular_name' => __('Stock alert', 'brandhouse')],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => 'edit.php?post_type=product',
        'capability_type' => 'post',
        'capabilities' => ['create_posts' => 'do_not_allow'],
        'map_meta_cap' => true,
        'supports' => ['title'],
    ]);
});

// Only shop managers see the list.
add_filter('user_has_cap', static function (array $all, array $caps, array $args): array {
    if (isset($args[2]) && get_post_type((int) $args[2]) === 'bh_stock_alert' && empty($all['manage_woocommerce'])) {
        foreach ($caps as $c) {
            $all[$c] = false;
        }
    }
    return $all;
}, 10, 3);

add_filter('manage_bh_stock_alert_posts_columns', static fn (): array => ['cb' => '<input type="checkbox">', 'title' => __('Email', 'brandhouse'), 'bh_product' => __('Product', 'brandhouse'), 'bh_status' => __('Status', 'brandhouse'), 'date' => __('Requested', 'brandhouse')]);
add_action('manage_bh_stock_alert_posts_custom_column', static function (string $col, int $id): void {
    if ($col === 'bh_product') {
        $p = wc_get_product((int) get_post_meta($id, '_bh_product', true));
        echo $p ? esc_html($p->get_formatted_name()) : '—';
    } elseif ($col === 'bh_status') {
        $sent = get_post_meta($id, '_bh_sent', true);
        echo $sent ? esc_html(sprintf(__('Emailed %s', 'brandhouse'), wp_date(get_option('date_format'), (int) $sent))) : esc_html__('Waiting', 'brandhouse');
    }
}, 10, 2);

/** Sold-out sizes (variation id => label) for a variable product, or [id => name] for a sold-out simple product. */
function brandhouse_sold_out_options(WC_Product $product): array
{
    if ($product->is_type('variable')) {
        $out = [];
        foreach ($product->get_children() as $vid) {
            $v = wc_get_product($vid);
            if ($v && !$v->is_in_stock()) {
                $out[$vid] = wc_get_formatted_variation($v, true, false, false);
            }
        }
        return $out;
    }
    return $product->is_in_stock() ? [] : [$product->get_id() => $product->get_name()];
}

// Product page: "Notify me" link (variable, some sizes sold out) or form (simple, sold out).
add_action('woocommerce_single_product_summary', static function (): void {
    global $product;
    if (!$product instanceof WC_Product) {
        return;
    }
    $options = brandhouse_sold_out_options($product);
    if (!$options) {
        return;
    }
    $status = sanitize_key(wp_unslash($_GET['bh_notify'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification -- display only
    if ($status === 'ok') {
        echo '<p class="notify-done" role="status">' . esc_html__('Thanks — we will email you when it is back in stock.', 'brandhouse') . '</p>';
    } elseif ($status === 'invalid') {
        echo '<p class="notify-done is-error" role="alert">' . esc_html__('Please enter a valid email address.', 'brandhouse') . '</p>';
    }
    $label = $product->is_type('variable') ? __('Size sold out? Notify me', 'brandhouse') : __('Notify me when available', 'brandhouse');
    printf('<button type="button" class="%s" data-dialog="notify-me">%s</button>', $product->is_type('variable') ? 'link-btn notify-open' : 'button btn-outline notify-open notify-block', esc_html($label));
    add_action('wp_footer', static function () use ($product, $options): void {
        ?>
        <dialog class="sheet" id="notify-me" aria-labelledby="notify-title">
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <div class="sheet-head">
              <h2 id="notify-title"><?php esc_html_e('Notify me', 'brandhouse'); ?></h2>
              <button type="button" class="icon-btn" data-dialog-close aria-label="<?php esc_attr_e('Close', 'brandhouse'); ?>"><?php echo brandhouse_icon('close'); ?></button>
            </div>
            <div class="sheet-body notify-body">
              <p><?php echo esc_html($product->get_name()); ?></p>
              <?php if (count($options) > 1 || $product->is_type('variable')): ?>
                <label for="notify-size"><?php esc_html_e('Size', 'brandhouse'); ?></label>
                <select id="notify-size" name="product_id" required>
                  <?php foreach ($options as $id => $name): ?><option value="<?php echo (int) $id; ?>"><?php echo esc_html($name); ?></option><?php endforeach; ?>
                </select>
              <?php else: ?>
                <input type="hidden" name="product_id" value="<?php echo (int) array_key_first($options); ?>">
              <?php endif; ?>
              <label for="notify-email"><?php esc_html_e('Email', 'brandhouse'); ?></label>
              <input id="notify-email" type="email" name="email" required autocomplete="email">
              <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true">
              <input type="hidden" name="action" value="bh_notify">
              <input type="hidden" name="back" value="<?php echo esc_url(get_permalink($product->get_id())); ?>">
              <?php wp_nonce_field('bh_notify', 'bh_notify_nonce'); ?>
              <p class="market-note"><?php esc_html_e('We only use your email to tell you when this item is back in stock.', 'brandhouse'); ?></p>
              <button class="btn notify-submit" type="submit"><?php esc_html_e('Notify me', 'brandhouse'); ?></button>
            </div>
          </form>
        </dialog>
        <?php
    });
}, 32);

function brandhouse_handle_notify(): void
{
    $back = esc_url_raw(wp_unslash($_POST['back'] ?? home_url('/')));
    $back = wp_validate_redirect($back, home_url('/'));
    if (!isset($_POST['bh_notify_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['bh_notify_nonce'])), 'bh_notify') || !empty($_POST['website'])) {
        wp_safe_redirect($back);
        exit;
    }
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $pid = absint($_POST['product_id'] ?? 0);
    $product = $pid ? wc_get_product($pid) : null;
    if (!is_email($email) || !$product) {
        wp_safe_redirect(add_query_arg('bh_notify', 'invalid', $back));
        exit;
    }
    // One waiting alert per email and product; a simple per-IP limit keeps the form from being abused.
    $ipkey = 'bh_notify_' . md5((string) WC_Geolocation::get_ip_address());
    $hits = (int) get_transient($ipkey);
    if ($hits < 20) {
        set_transient($ipkey, $hits + 1, HOUR_IN_SECONDS);
        $exists = get_posts(['post_type' => 'bh_stock_alert', 'post_status' => 'private', 'title' => $email, 'meta_query' => [['key' => '_bh_product', 'value' => $pid], ['key' => '_bh_sent', 'compare' => 'NOT EXISTS']], 'fields' => 'ids', 'numberposts' => 1]);
        if (!$exists) {
            $id = wp_insert_post(['post_type' => 'bh_stock_alert', 'post_status' => 'private', 'post_title' => $email]);
            update_post_meta($id, '_bh_product', $pid);
        }
    }
    wp_safe_redirect(add_query_arg('bh_notify', 'ok', $back));
    exit;
}
add_action('admin_post_bh_notify', 'brandhouse_handle_notify');
add_action('admin_post_nopriv_bh_notify', 'brandhouse_handle_notify');

// When a product or size comes back in stock, email everyone waiting for it.
function brandhouse_send_stock_alerts(int $pid, string $status): void
{
    if ($status !== 'instock') {
        return;
    }
    $product = wc_get_product($pid);
    if (!$product) {
        return;
    }
    $alerts = get_posts(['post_type' => 'bh_stock_alert', 'post_status' => 'private', 'meta_query' => [['key' => '_bh_product', 'value' => $pid], ['key' => '_bh_sent', 'compare' => 'NOT EXISTS']], 'numberposts' => 200]);
    $site = get_bloginfo('name');
    foreach ($alerts as $a) {
        $sent = wp_mail(
            $a->post_title,
            sprintf(__('%1$s is back in stock at %2$s', 'brandhouse'), $product->get_name(), $site),
            sprintf(__("Good news — %1\$s is available again.\n\nShop it here: %2\$s\n\n%3\$s", 'brandhouse'), $product->get_formatted_name() ? wp_strip_all_tags($product->get_formatted_name()) : $product->get_name(), get_permalink($product->get_parent_id() ?: $product->get_id()), $site)
        );
        if ($sent) {
            update_post_meta($a->ID, '_bh_sent', time());
        }
    }
}
add_action('woocommerce_product_set_stock_status', static fn ($id, $status) => brandhouse_send_stock_alerts((int) $id, (string) $status), 10, 2);
add_action('woocommerce_variation_set_stock_status', static fn ($id, $status) => brandhouse_send_stock_alerts((int) $id, (string) $status), 10, 2);

/* ── Free-shipping progress (cart) ───────────────────────────────────────────────────────────── */

/** Minimum order for free shipping from the store's own Free shipping method in the customer's zone, or null. */
function brandhouse_free_shipping_min(): ?float
{
    $country = WC()->customer ? (WC()->customer->get_shipping_country() ?: WC()->customer->get_billing_country()) : '';
    $zone = WC_Shipping_Zones::get_zone_matching_package(['destination' => ['country' => $country ?: WC()->countries->get_base_country(), 'state' => '', 'postcode' => '']]);
    foreach ($zone->get_shipping_methods(true) as $method) {
        if ($method->id === 'free_shipping' && in_array($method->get_option('requires'), ['min_amount', 'either'], true)) {
            $min = (float) $method->get_option('min_amount');
            return $min > 0 ? $min : null;
        }
    }
    return null;
}

add_filter('render_block_woocommerce/cart', static function (string $html): string {
    $min = brandhouse_free_shipping_min();
    if ($min === null) {
        return $html;
    }
    $bar = sprintf(
        '<div class="ship-bar" data-free-min="%s" data-currency="%s" data-tpl-more="%s" data-tpl-done="%s"><p class="ship-msg" aria-live="polite"></p><div class="ship-track"><span class="ship-fill"></span></div></div>',
        esc_attr((string) $min),
        esc_attr(html_entity_decode(get_woocommerce_currency_symbol())),
        esc_attr__('Add %s more for free shipping', 'brandhouse'),
        esc_attr__('You have free shipping', 'brandhouse')
    );
    return $bar . $html;
});

/* ── Drop countdown (homepage) ───────────────────────────────────────────────────────────────── */

add_action('customize_register', static function (WP_Customize_Manager $c): void {
    $c->add_section('brandhouse_drop', ['title' => __('Drop countdown', 'brandhouse'), 'priority' => 31, 'description' => __('Shown on the homepage until the date passes. Leave the date empty to hide it.', 'brandhouse')]);
    $c->add_setting('brandhouse_drop_title', ['sanitize_callback' => 'sanitize_text_field']);
    $c->add_control('brandhouse_drop_title', ['label' => __('Drop name', 'brandhouse'), 'section' => 'brandhouse_drop', 'type' => 'text']);
    $c->add_setting('brandhouse_drop_at', ['sanitize_callback' => static fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', (string) $v) ? $v : '']);
    $c->add_control('brandhouse_drop_at', ['label' => __('Drop date and time (store time zone)', 'brandhouse'), 'section' => 'brandhouse_drop', 'type' => 'datetime-local']);
    $c->add_setting('brandhouse_drop_link', ['sanitize_callback' => 'esc_url_raw']);
    $c->add_control('brandhouse_drop_link', ['label' => __('Link (optional)', 'brandhouse'), 'section' => 'brandhouse_drop', 'type' => 'url']);
});

function brandhouse_drop_banner(): void
{
    $at = (string) get_theme_mod('brandhouse_drop_at', '');
    if ($at === '') {
        return;
    }
    $when = date_create_immutable($at, wp_timezone());
    if (!$when || $when->getTimestamp() <= time()) {
        return;
    }
    $title = brandhouse_mod('brandhouse_drop_title', __('Next drop', 'brandhouse'));
    $link = (string) get_theme_mod('brandhouse_drop_link', '');
    $units = ['d' => __('Days', 'brandhouse'), 'h' => __('Hours', 'brandhouse'), 'm' => __('Min', 'brandhouse'), 's' => __('Sec', 'brandhouse')];
    ?>
    <section class="drop" aria-label="<?php echo esc_attr($title); ?>">
      <div class="drop-inner container">
        <div class="drop-text">
          <p class="eyebrow"><?php esc_html_e('Coming soon', 'brandhouse'); ?></p>
          <h2><?php echo esc_html($title); ?></h2>
          <p class="drop-date"><?php echo esc_html(wp_date(get_option('date_format') . ' · ' . get_option('time_format'), $when->getTimestamp())); ?></p>
        </div>
        <div class="drop-clock" data-deadline="<?php echo esc_attr((string) ($when->getTimestamp() * 1000)); ?>" role="timer">
          <?php foreach ($units as $k => $u): ?><div><span data-u="<?php echo esc_attr($k); ?>">--</span><small><?php echo esc_html($u); ?></small></div><?php endforeach; ?>
        </div>
        <?php if ($link !== ''): ?><a class="btn btn-light" href="<?php echo esc_url($link); ?>"><?php esc_html_e('Preview', 'brandhouse'); ?></a><?php endif; ?>
      </div>
    </section>
    <?php
}
add_action('brandhouse_home_after_hero', 'brandhouse_drop_banner');
