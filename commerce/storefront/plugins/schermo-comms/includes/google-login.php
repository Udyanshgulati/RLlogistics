<?php
/**
 * Sign in with Google (OpenID Connect, authorization-code flow). No extra plugin or library.
 * Shown only when the server env has GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET (never stored in the database or repo).
 * Owner setup (Google Cloud Console → APIs & Services → Credentials → OAuth client ID, type "Web application"):
 *   Authorised redirect URI:  https://schermo.thodalogic.com/?scm_google=callback
 * Flow: /?scm_google=start → Google → /?scm_google=callback&code&state. The state is a random value bound to a
 * short-lived cookie (CSRF); the code is exchanged server-to-server; the ID token's audience, issuer, expiry and
 * verified email are checked. An existing customer with that verified email is signed in (and linked to the Google
 * account); otherwise a WooCommerce customer account is created. No password is ever shown or emailed.
 */

defined('ABSPATH') || exit;

function scm_google_ready(): bool
{
    return (string) getenv('GOOGLE_CLIENT_ID') !== '' && (string) getenv('GOOGLE_CLIENT_SECRET') !== '';
}

function scm_google_redirect_uri(): string
{
    return add_query_arg('scm_google', 'callback', home_url('/'));
}

/** The button (account login/register forms). */
function scm_google_button(): void
{
    if (!scm_google_ready() || is_user_logged_in()) {
        return;
    }
    $back = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : (is_checkout() ? wc_get_checkout_url() : wc_get_page_permalink('myaccount')); // phpcs:ignore WordPress.Security.NonceVerification
    $url = add_query_arg(['scm_google' => 'start', 'back' => rawurlencode($back)], home_url('/'));
    echo '<a class="btn btn-outline g-signin" href="' . esc_url($url) . '"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 48 48"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg><span>' . esc_html__('Continue with Google', 'schermo-comms') . '</span></a><p class="g-or"><span>' . esc_html__('or', 'schermo-comms') . '</span></p>';
}
add_action('woocommerce_login_form_start', 'scm_google_button');
add_action('woocommerce_register_form_start', 'scm_google_button');

add_action('template_redirect', static function (): void {
    $step = isset($_GET['scm_google']) ? sanitize_key(wp_unslash($_GET['scm_google'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- OAuth state below
    if ($step === '' || !scm_google_ready()) {
        return;
    }
    $account = wc_get_page_permalink('myaccount');
    $fail = static function (string $why) use ($account): void {
        wc_add_notice($why, 'error');
        wp_safe_redirect($account);
        exit;
    };
    if ($step === 'start') {
        $state = bin2hex(random_bytes(16));
        $back = isset($_GET['back']) ? wp_validate_redirect(esc_url_raw(rawurldecode(wp_unslash($_GET['back']))), $account) : $account; // phpcs:ignore
        set_transient('scm_g_' . hash('sha256', $state), ['back' => $back], 10 * MINUTE_IN_SECONDS);
        setcookie('scm_g_state', $state, ['expires' => time() + 600, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax']);
        wp_redirect(add_query_arg(array_map('rawurlencode', [ // phpcs:ignore WordPress.Security.SafeRedirect -- Google's endpoint
            'client_id' => (string) getenv('GOOGLE_CLIENT_ID'),
            'redirect_uri' => scm_google_redirect_uri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]), 'https://accounts.google.com/o/oauth2/v2/auth'));
        exit;
    }
    if ($step !== 'callback') {
        return;
    }
    $state = sanitize_text_field(wp_unslash($_GET['state'] ?? '')); // phpcs:ignore
    $cookie = sanitize_text_field(wp_unslash($_COOKIE['scm_g_state'] ?? ''));
    $saved = $state !== '' ? get_transient('scm_g_' . hash('sha256', $state)) : false;
    setcookie('scm_g_state', '', ['expires' => time() - 3600, 'path' => '/']);
    if (!$saved || $cookie === '' || !hash_equals($cookie, $state)) {
        $fail(__('Google sign-in expired or was not started here. Please try again.', 'schermo-comms'));
    }
    delete_transient('scm_g_' . hash('sha256', $state));
    $code = sanitize_text_field(wp_unslash($_GET['code'] ?? '')); // phpcs:ignore
    if ($code === '') {
        $fail(__('Google sign-in was cancelled.', 'schermo-comms'));
    }
    $res = wp_remote_post('https://oauth2.googleapis.com/token', ['timeout' => 15, 'body' => [
        'code' => $code, 'client_id' => getenv('GOOGLE_CLIENT_ID'), 'client_secret' => getenv('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => scm_google_redirect_uri(), 'grant_type' => 'authorization_code',
    ]]);
    $tok = is_wp_error($res) ? [] : (array) json_decode((string) wp_remote_retrieve_body($res), true);
    $parts = explode('.', (string) ($tok['id_token'] ?? ''));
    $claims = count($parts) === 3 ? (array) json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true) : [];
    // The token came straight from Google over TLS in exchange for our secret, so its claims are checked, not re-signed.
    $ok = ($claims['aud'] ?? '') === getenv('GOOGLE_CLIENT_ID')
        && in_array($claims['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true)
        && (int) ($claims['exp'] ?? 0) > time()
        && !empty($claims['email']) && !empty($claims['email_verified']) && !empty($claims['sub']);
    if (!$ok) {
        $fail(__('Google sign-in could not be verified. Please try again or use your email and password.', 'schermo-comms'));
    }
    $email = strtolower((string) $claims['email']);
    $sub = (string) $claims['sub'];
    $linked = get_users(['meta_key' => '_scm_google_sub', 'meta_value' => $sub, 'number' => 1, 'fields' => 'ID']);
    $uid = $linked ? (int) $linked[0] : (int) email_exists($email);
    if (!$uid) {
        $uid = wc_create_new_customer($email, '', wp_generate_password(32), ['first_name' => (string) ($claims['given_name'] ?? ''), 'last_name' => (string) ($claims['family_name'] ?? '')]);
        if (is_wp_error($uid)) {
            $fail($uid->get_error_message());
        }
    }
    if (user_can($uid, 'edit_posts')) {
        // Staff accounts keep using their password (and any 2FA) — never a one-click Google login.
        $fail(__('This email belongs to a staff account. Please sign in with your password.', 'schermo-comms'));
    }
    update_user_meta($uid, '_scm_google_sub', $sub);
    wp_set_current_user($uid);
    wp_set_auth_cookie($uid, true, is_ssl());
    do_action('wp_login', get_userdata($uid)->user_login, get_userdata($uid));
    wp_safe_redirect($saved['back'] ?: $account);
    exit;
});
