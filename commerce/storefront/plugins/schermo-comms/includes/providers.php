<?php
/**
 * Provider adapters. Credentials come only from the server environment (never the database or the repo):
 *   Email:     SMTP_HOST, SMTP_USER, SMTP_PASS … (already used by docker/schermo-mail.php for all site email)
 *   WhatsApp:  WA_PHONE_NUMBER_ID, WA_ACCESS_TOKEN (WhatsApp Business Platform / Cloud API),
 *              WA_APP_SECRET (webhook signature check), WA_VERIFY_TOKEN (webhook verification)
 * In dry-run mode nothing leaves the site: the rendered message is stored in the log as "simulated".
 * A send returns ['ok' => bool, 'id' => provider message id, 'error' => text, 'retry' => bool].
 */

defined('ABSPATH') || exit;

function scm_wa_ready(): bool
{
    return (string) getenv('WA_PHONE_NUMBER_ID') !== '' && (string) getenv('WA_ACCESS_TOKEN') !== '';
}

function scm_email_ready(): bool
{
    return function_exists('schermo_mail_ready') ? schermo_mail_ready() : false;
}

/** Live only when the owner switched it on AND the channel is configured. */
function scm_live(string $channel): bool
{
    return scm_settings()['mode'] === 'live' && ($channel === 'email' ? scm_email_ready() : scm_wa_ready());
}

function scm_send_email(array $msg): array
{
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    if ($msg['purpose'] === 'marketing') {
        $u = scm_unsubscribe_url('email', $msg['recipient']);
        $headers[] = 'List-Unsubscribe: <' . $u . '>';
        $headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
    }
    $ok = wp_mail($msg['recipient'], $msg['subject'], $msg['html'], $headers);
    $err = $ok ? '' : (string) (get_option('schermo_mail_error')['error'] ?? 'wp_mail failed');
    return ['ok' => $ok, 'id' => '', 'error' => $err, 'retry' => !$ok];
}

/**
 * WhatsApp Cloud API: business-initiated messages must use an approved template. Template name, language and the
 * ordered body parameters come from the message; without an approved template name the message is not sent.
 */
function scm_send_whatsapp(array $msg): array
{
    if (empty($msg['wa_template'])) {
        return ['ok' => false, 'id' => '', 'error' => 'no approved WhatsApp template configured for this event', 'retry' => false];
    }
    $params = array_map(static fn ($v) => ['type' => 'text', 'text' => (string) $v], $msg['wa_params'] ?? []);
    $body = [
        'messaging_product' => 'whatsapp',
        'to' => ltrim($msg['recipient'], '+'),
        'type' => 'template',
        'template' => ['name' => $msg['wa_template'], 'language' => ['code' => $msg['wa_lang'] ?: 'en'], 'components' => $params ? [['type' => 'body', 'parameters' => $params]] : []],
    ];
    $res = wp_remote_post('https://graph.facebook.com/v21.0/' . rawurlencode((string) getenv('WA_PHONE_NUMBER_ID')) . '/messages', [
        'timeout' => 15,
        'headers' => ['Authorization' => 'Bearer ' . getenv('WA_ACCESS_TOKEN'), 'Content-Type' => 'application/json'],
        'body' => wp_json_encode($body),
    ]);
    if (is_wp_error($res)) {
        return ['ok' => false, 'id' => '', 'error' => $res->get_error_message(), 'retry' => true];
    }
    $code = (int) wp_remote_retrieve_response_code($res);
    $json = json_decode((string) wp_remote_retrieve_body($res), true);
    if ($code >= 200 && $code < 300 && !empty($json['messages'][0]['id'])) {
        return ['ok' => true, 'id' => (string) $json['messages'][0]['id'], 'error' => '', 'retry' => false];
    }
    $err = (string) ($json['error']['message'] ?? "HTTP $code");
    // Template problems (not approved, paused, wrong params) and bad numbers will not fix themselves: no retry.
    return ['ok' => false, 'id' => '', 'error' => $err, 'retry' => $code >= 500 || $code === 429];
}

// ---- WhatsApp webhook: delivery statuses and opt-outs ("STOP"). URL: /wp-json/scm/v1/whatsapp
add_action('rest_api_init', static function (): void {
    register_rest_route('scm/v1', '/whatsapp', [
        [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => static function (WP_REST_Request $r) {
                $ok = getenv('WA_VERIFY_TOKEN') && hash_equals((string) getenv('WA_VERIFY_TOKEN'), (string) $r->get_param('hub_verify_token')) && $r->get_param('hub_mode') === 'subscribe';
                if (!$ok) {
                    return new WP_REST_Response('forbidden', 403);
                }
                header('Content-Type: text/plain');
                echo esc_html((string) $r->get_param('hub_challenge'));
                exit;
            },
        ],
        [
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => static function (WP_REST_Request $r) {
                $secret = (string) getenv('WA_APP_SECRET');
                $sig = (string) $r->get_header('x_hub_signature_256');
                if ($secret === '' || !hash_equals('sha256=' . hash_hmac('sha256', $r->get_body(), $secret), $sig)) {
                    return new WP_REST_Response('bad signature', 403);
                }
                global $wpdb;
                $data = json_decode($r->get_body(), true);
                foreach ($data['entry'] ?? [] as $entry) {
                    foreach ($entry['changes'] ?? [] as $ch) {
                        $v = $ch['value'] ?? [];
                        foreach ($v['statuses'] ?? [] as $st) {
                            $wpdb->update($wpdb->prefix . 'scm_messages', ['status' => sanitize_key($st['status'] ?? 'unknown'), 'updated_at' => scm_now()], ['provider_id' => sanitize_text_field($st['id'] ?? '')]);
                        }
                        foreach ($v['messages'] ?? [] as $m) {
                            $text = strtoupper(trim((string) ($m['text']['body'] ?? $m['button']['text'] ?? '')));
                            if (in_array($text, ['STOP', 'UNSUBSCRIBE', 'STOP PROMOTIONS'], true)) {
                                scm_consent_set('whatsapp', 'marketing', '+' . ltrim((string) ($m['from'] ?? ''), '+'), false, 'whatsapp-stop');
                            }
                        }
                    }
                }
                return new WP_REST_Response('ok', 200);
            },
        ],
    ]);
});
