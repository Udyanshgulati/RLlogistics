<?php
/**
 * Newsletter sign-up with a welcome offer. POST /wp-json/scm/v1/newsletter {email, consent}
 * - Requires the visitor to tick the marketing consent box (recorded with source, wording version and time).
 * - Creates one personal WooCommerce coupon for that email: percentage from settings (default 10%), single use,
 *   valid 30 days, not on items already on sale, not combinable. Signing up again returns the same code.
 * - Queues the welcome message (dry-run until providers are connected) with the code.
 * - Light abuse limits: 5 sign-ups per IP per hour; no account or personal data beyond the email is stored.
 */

defined('ABSPATH') || exit;

function scm_offer(): array
{
    $s = scm_settings();
    return ['on' => (int) ($s['newsletter_offer_on'] ?? 1) === 1, 'percent' => max(1, min(50, (int) ($s['newsletter_offer_percent'] ?? 10))), 'days' => 30];
}

add_action('rest_api_init', static function (): void {
    register_rest_route('scm/v1', '/newsletter', [
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'callback' => static function (WP_REST_Request $r) {
            $email = strtolower(trim(sanitize_email((string) $r->get_param('email'))));
            if (!is_email($email)) {
                return new WP_REST_Response(['ok' => false, 'error' => __('Please enter a valid email address.', 'schermo-comms')], 400);
            }
            if (!$r->get_param('consent')) {
                return new WP_REST_Response(['ok' => false, 'error' => __('Please tick the box to agree to receive our emails.', 'schermo-comms')], 400);
            }
            $ip = md5((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
            $hits = (int) get_transient('scm_nl_' . $ip);
            if ($hits >= 5) {
                return new WP_REST_Response(['ok' => false, 'error' => __('Too many sign-ups from this connection. Please try again later.', 'schermo-comms')], 429);
            }
            set_transient('scm_nl_' . $ip, $hits + 1, HOUR_IN_SECONDS);

            $offer = scm_offer();
            $code = '';
            if ($offer['on']) {
                $codes = get_option('scm_nl_codes', []);
                $key = hash('sha256', $email);
                $code = (string) ($codes[$key] ?? '');
                $existing = $code !== '' ? new WC_Coupon($code) : null;
                if (!$existing || !$existing->get_id()) {
                    $code = 'SCHERMO-' . strtoupper(wp_generate_password(6, false, false));
                    $c = new WC_Coupon();
                    $c->set_code($code);
                    $c->set_discount_type('percent');
                    $c->set_amount((string) $offer['percent']);
                    $c->set_individual_use(true);
                    $c->set_usage_limit(1);
                    $c->set_usage_limit_per_user(1);
                    $c->set_email_restrictions([$email]);
                    $c->set_exclude_sale_items(true);
                    $c->set_date_expires(time() + $offer['days'] * DAY_IN_SECONDS);
                    $c->set_description('Newsletter welcome offer (schermo-comms)');
                    $c->save();
                    $codes[$key] = $code;
                    update_option('scm_nl_codes', $codes, false);
                }
            }
            // Consent record (+ welcome message via the scm_marketing_opt_in action, once per contact).
            add_filter('scm_welcome_vars', static fn (array $v) => $v + ['coupon_line' => $code !== '' ? sprintf(__('Your welcome code: %1$s — %2$d%% off your first order, valid for %3$d days (not on sale items).', 'schermo-comms'), $code, $offer['percent'], $offer['days']) : '']);
            scm_consent_set('email', 'marketing', $email, true, 'newsletter-popup', ['user_id' => get_current_user_id()]);
            return new WP_REST_Response(['ok' => true, 'code' => $code, 'percent' => $offer['percent'], 'days' => $offer['days']], 200);
        },
    ]);
});
