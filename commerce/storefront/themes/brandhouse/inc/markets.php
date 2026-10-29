<?php
/**
 * Markets: visitor country → display currency.
 *
 * - First visit: the country is detected from the visitor's IP (WooCommerce geolocation) and a popup asks the
 *   visitor to confirm or change it. The choice is kept in a cookie; it can be changed from the header any time.
 * - Prices on shop pages are shown converted with live rates (fetched, cached 12 h, never hard-coded). If rates
 *   are older than 48 h or unavailable, prices fall back to the store currency.
 * - Cart, checkout, orders, emails and payment stay in the store currency (INR): conversion is display-only, and
 *   the store says so wherever converted prices are shown.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

if (!class_exists('WooCommerce')) {
    return;
}

const BRANDHOUSE_MARKET_COOKIE = 'bh_market';
const BRANDHOUSE_RATES_MAX_AGE = 48 * HOUR_IN_SECONDS;

/** Markets offered in the selector. Child themes or plugins can change them with the `brandhouse_markets` filter. */
function brandhouse_markets(): array
{
    $markets = [
        'IN' => ['label' => __('India', 'brandhouse'), 'currency' => 'INR', 'countries' => ['IN']],
        'US' => ['label' => __('United States', 'brandhouse'), 'currency' => 'USD', 'countries' => ['US']],
        'GB' => ['label' => __('United Kingdom', 'brandhouse'), 'currency' => 'GBP', 'countries' => ['GB']],
        'EU' => ['label' => __('Europe', 'brandhouse'), 'currency' => 'EUR', 'countries' => ['AT', 'BE', 'BG', 'CY', 'DE', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PT', 'SI', 'SK']],
        'AE' => ['label' => __('United Arab Emirates', 'brandhouse'), 'currency' => 'AED', 'countries' => ['AE']],
        'INTL' => ['label' => __('Rest of the world', 'brandhouse'), 'currency' => 'USD', 'countries' => []],
    ];
    return (array) apply_filters('brandhouse_markets', $markets);
}

function brandhouse_market_for_country(string $country): string
{
    foreach (brandhouse_markets() as $id => $m) {
        if (in_array(strtoupper($country), $m['countries'], true)) {
            return $id;
        }
    }
    return array_key_exists('INTL', brandhouse_markets()) ? 'INTL' : (string) array_key_first(brandhouse_markets());
}

/** Market chosen by the visitor (cookie), or null on a first visit. */
function brandhouse_chosen_market(): ?string
{
    $id = isset($_COOKIE[BRANDHOUSE_MARKET_COOKIE]) ? strtoupper(sanitize_key(wp_unslash($_COOKIE[BRANDHOUSE_MARKET_COOKIE]))) : '';
    return $id !== '' && array_key_exists($id, brandhouse_markets()) ? $id : null;
}

/** Country detected from the visitor's IP; '' when it cannot be determined. Cached per request. */
function brandhouse_detected_country(): string
{
    static $country = null;
    if ($country === null) {
        $geo = WC_Geolocation::geolocate_ip('', true, true);
        $country = strtoupper((string) ($geo['country'] ?? ''));
    }
    return $country;
}

/** Active market: chosen one, else the detected one (used until the visitor confirms in the popup). */
function brandhouse_market(): string
{
    return brandhouse_chosen_market() ?? brandhouse_market_for_country(brandhouse_detected_country() ?: get_option('woocommerce_default_country', 'IN'));
}

/** Rates from the store currency, or null when unavailable or older than 48 hours. */
function brandhouse_rates(): ?array
{
    $base = get_option('woocommerce_currency');
    $cached = get_transient('brandhouse_rates_' . $base);
    if (!is_array($cached)) {
        $res = wp_remote_get('https://open.er-api.com/v6/latest/' . rawurlencode($base), ['timeout' => 4]);
        $body = is_wp_error($res) ? null : json_decode((string) wp_remote_retrieve_body($res), true);
        if (is_array($body) && ($body['result'] ?? '') === 'success' && is_array($body['rates'] ?? null)) {
            $cached = ['rates' => array_map('floatval', $body['rates']), 'updated' => (int) ($body['time_last_update_unix'] ?? time())];
            update_option('brandhouse_rates_last_good_' . $base, $cached, false);
        } else {
            $cached = get_option('brandhouse_rates_last_good_' . $base) ?: ['rates' => [], 'updated' => 0];
        }
        set_transient('brandhouse_rates_' . $base, $cached, is_wp_error($res) ? 15 * MINUTE_IN_SECONDS : 12 * HOUR_IN_SECONDS);
    }
    return $cached['updated'] > time() - BRANDHOUSE_RATES_MAX_AGE && $cached['rates'] ? $cached : null;
}

/** Currency prices are displayed in for this visitor (store currency when no fresh rate exists). */
function brandhouse_display_currency(): string
{
    $base = get_option('woocommerce_currency');
    $want = brandhouse_markets()[brandhouse_market()]['currency'] ?? $base;
    if ($want === $base) {
        return $base;
    }
    $rates = brandhouse_rates();
    return isset($rates['rates'][$want]) && $rates['rates'][$want] > 0 ? $want : $base;
}

/** Conversion applies to browsing pages only, never to cart, checkout, account, admin, REST or AJAX. */
function brandhouse_converts_prices(): bool
{
    if (is_admin() || wp_doing_ajax() || wp_is_serving_rest_request() || wp_doing_cron()) {
        return false;
    }
    if (is_cart() || is_checkout() || is_account_page()) {
        return false;
    }
    return brandhouse_display_currency() !== get_option('woocommerce_currency');
}

add_filter('wc_price', static function (string $html, $price, array $args, $unformatted) {
    static $inside = false;
    if ($inside || !did_action('wp') || !brandhouse_converts_prices() || !is_numeric($unformatted)) {
        return $html;
    }
    $currency = brandhouse_display_currency();
    $rate = brandhouse_rates()['rates'][$currency];
    $inside = true;
    $out = wc_price((float) $unformatted * $rate, array_merge($args, ['currency' => $currency, 'decimals' => 2]));
    $inside = false;
    return $out;
}, 10, 4);

// Handle the selector form: ?bh_market=US sets the cookie and returns to the same page without the parameter.
add_action('template_redirect', static function (): void {
    if (!isset($_GET['bh_market'])) {
        return;
    }
    $id = strtoupper(sanitize_key(wp_unslash($_GET['bh_market'])));
    if (array_key_exists($id, brandhouse_markets())) {
        setcookie(BRANDHOUSE_MARKET_COOKIE, $id, ['expires' => time() + YEAR_IN_SECONDS, 'path' => COOKIEPATH ?: '/', 'secure' => is_ssl(), 'httponly' => false, 'samesite' => 'Lax']);
        $_COOKIE[BRANDHOUSE_MARKET_COOKIE] = $id;
        // Pre-fill the checkout country with the chosen market's country.
        $countries = brandhouse_markets()[$id]['countries'];
        if (count($countries) === 1 && WC()->customer) {
            WC()->customer->set_shipping_country($countries[0]);
            WC()->customer->set_billing_country($countries[0]);
            WC()->customer->save();
        }
    }
    wp_safe_redirect(remove_query_arg('bh_market'));
    exit;
});

function brandhouse_currency_label(string $code): string
{
    return html_entity_decode(get_woocommerce_currency_symbol($code)) . ' ' . $code;
}

/** Header/footer/drawer button showing the active market; opens the selector. */
function brandhouse_market_button(string $class = ''): string
{
    $m = brandhouse_markets()[brandhouse_market()];
    return sprintf(
        '<button type="button" class="market-btn %s" data-dialog="market-picker" aria-haspopup="dialog">%s<span>%s · %s</span></button>',
        esc_attr($class),
        brandhouse_icon('globe'),
        esc_html($m['label']),
        esc_html(brandhouse_currency_label(brandhouse_display_currency()))
    );
}

/** The market dialog. On a first visit it opens automatically, pre-selected with the detected country. */
add_action('wp_footer', static function (): void {
    $active = brandhouse_market();
    $first = brandhouse_chosen_market() === null;
    $detected = brandhouse_detected_country();
    $countries = WC()->countries->get_countries();
    $base = get_option('woocommerce_currency');
    ?>
    <dialog class="sheet market-sheet" id="market-picker" aria-labelledby="market-title"<?php echo $first ? ' data-autoopen' : ''; ?>>
      <form method="get" action="">
        <div class="sheet-head">
          <h2 id="market-title"><?php esc_html_e('Where are you shopping from?', 'brandhouse'); ?></h2>
          <button type="button" class="icon-btn" data-dialog-close aria-label="<?php esc_attr_e('Close', 'brandhouse'); ?>"><?php echo brandhouse_icon('close'); ?></button>
        </div>
        <div class="sheet-body">
          <?php if ($first && $detected !== '' && isset($countries[$detected])): ?>
            <p class="market-detected"><?php printf(esc_html__('It looks like you are in %s.', 'brandhouse'), '<strong>' . esc_html($countries[$detected]) . '</strong>'); ?></p>
          <?php endif; ?>
          <fieldset class="market-list">
            <legend class="sr-only"><?php esc_html_e('Country or region', 'brandhouse'); ?></legend>
            <?php foreach (brandhouse_markets() as $id => $m): ?>
              <label class="market-opt">
                <input type="radio" name="bh_market" value="<?php echo esc_attr($id); ?>"<?php checked($id, $active); ?><?php echo $id === $active ? ' autofocus' : ''; ?>>
                <span class="market-name"><?php echo esc_html($m['label']); ?></span>
                <span class="market-cur"><?php echo esc_html(brandhouse_currency_label($m['currency'])); ?></span>
              </label>
            <?php endforeach; ?>
          </fieldset>
          <p class="market-note"><?php printf(esc_html__('Prices are shown in your currency using current exchange rates. Orders are charged in %s; your bank converts at its own rate.', 'brandhouse'), esc_html($base)); ?></p>
          <button class="btn market-go" type="submit"><?php esc_html_e('Continue', 'brandhouse'); ?></button>
        </div>
      </form>
    </dialog>
    <?php
});

// On cart/checkout, say plainly which currency is charged when the visitor browses in another one.
add_action('wp_body_open', static function (): void {
    if (!(is_cart() || is_checkout()) || brandhouse_display_currency() === get_option('woocommerce_currency')) {
        return;
    }
    printf('<div class="announce market-charge"><p>%s</p></div>', esc_html(sprintf(__('Your order will be charged in %s.', 'brandhouse'), get_option('woocommerce_currency'))));
});
