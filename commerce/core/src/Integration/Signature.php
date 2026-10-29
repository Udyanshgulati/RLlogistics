<?php

declare(strict_types=1);

namespace Commerce\Core\Integration;

/**
 * Request authentication between storefronts and the hub.
 *
 * Connector → hub calls carry X-Hub-Store, X-Hub-Timestamp and
 *   X-Hub-Signature = hex(HMAC-SHA256(secret, timestamp . "\n" . METHOD . "\n" . path . "\n" . body))
 * with a per-store secret from STORE_<CODE>_HUB_SECRET. Requests older than 5 minutes are refused.
 *
 * WooCommerce's own webhooks are verified with its scheme: X-WC-Webhook-Signature = base64(HMAC-SHA256(secret, body)).
 */
final class Signature
{
    public const MAX_SKEW_SECONDS = 300;

    public static function sign(string $secret, int $timestamp, string $method, string $path, string $body): string
    {
        return hash_hmac('sha256', $timestamp . "\n" . strtoupper($method) . "\n" . $path . "\n" . $body, $secret);
    }

    public static function verify(string $secret, ?string $timestamp, ?string $signature, string $method, string $path, string $body, int $now): bool
    {
        if ($secret === '' || $timestamp === null || $signature === null || !ctype_digit($timestamp)) {
            return false;
        }
        if (abs($now - (int) $timestamp) > self::MAX_SKEW_SECONDS) {
            return false;
        }
        return hash_equals(self::sign($secret, (int) $timestamp, $method, $path, $body), strtolower($signature));
    }

    public static function verifyWooWebhook(string $secret, ?string $signature, string $body): bool
    {
        return $secret !== '' && $signature !== null && hash_equals(base64_encode(hash_hmac('sha256', $body, $secret, true)), $signature);
    }
}
