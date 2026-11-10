<?php
/** Signed HTTP between the Hub and its storefronts (HMAC-SHA256 over timestamp, method, path and body). */

declare(strict_types=1);

defined('ABSPATH') || exit;

function tli_mode(): string
{
    return get_option('tli_mode', 'hub') === 'store' ? 'store' : 'hub';
}

function tli_sign(string $secret, string $ts, string $method, string $path, string $body): string
{
    return hash_hmac('sha256', $ts . "\n" . strtoupper($method) . "\n" . $path . "\n" . $body, $secret);
}

/** Sends a signed request; returns the decoded JSON or throws with a readable message. */
function tli_call(string $base, string $id, string $secret, string $method, string $route, ?array $data = null): array
{
    $path = '/wp-json/tlhub/v1/' . ltrim($route, '/');
    $body = $data === null ? '' : (string) wp_json_encode($data);
    $ts = (string) time();
    $res = wp_remote_request(rtrim($base, '/') . $path, [
        'method' => $method, 'timeout' => 15, 'body' => $body === '' ? null : $body,
        'headers' => ['Content-Type' => 'application/json', 'X-TL-Store' => $id, 'X-TL-Timestamp' => $ts, 'X-TL-Signature' => tli_sign($secret, $ts, $method, $path, $body)],
    ]);
    if (is_wp_error($res)) {
        throw new RuntimeException('Could not reach ' . wp_parse_url($base, PHP_URL_HOST) . ': ' . $res->get_error_message());
    }
    $code = (int) wp_remote_retrieve_response_code($res);
    $json = json_decode((string) wp_remote_retrieve_body($res), true);
    if ($code < 200 || $code >= 300 || !is_array($json)) {
        throw new RuntimeException(sprintf('%s replied %d: %s', wp_parse_url($base, PHP_URL_HOST), $code, is_array($json) ? ($json['message'] ?? 'error') : 'not a valid response'));
    }
    return $json;
}

/** Checks an incoming signed request against a secret. */
function tli_verify(WP_REST_Request $r, string $secret): bool
{
    $ts = (string) $r->get_header('x_tl_timestamp');
    $sig = (string) $r->get_header('x_tl_signature');
    if ($ts === '' || $sig === '' || abs(time() - (int) $ts) > 300) {
        return false;
    }
    $path = (string) wp_parse_url(rest_url($r->get_route()), PHP_URL_PATH);
    return hash_equals(tli_sign($secret, $ts, $r->get_method(), $path, (string) $r->get_body()), $sig);
}

/** Paid order statuses on a storefront: the moment stock is taken from the central pool. */
const TLI_PAID = ['processing', 'tl-packed', 'tl-dispatched', 'completed', 'on-hold'];
const TLI_RELEASED = ['cancelled', 'refunded', 'failed'];
