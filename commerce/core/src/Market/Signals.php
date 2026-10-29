<?php

declare(strict_types=1);

namespace Commerce\Core\Market;

/**
 * Everything the storefront knows about where a shopper might be. All fields are ISO country codes or null.
 * The storefront connector fills them; it passes $cdnCountry only when the request really came through
 * the configured CDN (REMOTE_ADDR in the CDN's published ranges), otherwise the header is attacker-controlled.
 */
final class Signals
{
    public function __construct(
        public readonly ?string $sessionChoice = null,   // picked in the selector during this visit
        public readonly ?string $savedPreference = null, // account setting or long-lived preference cookie
        public readonly ?string $cdnCountry = null,      // e.g. Cloudflare CF-IPCountry, trusted only from CDN IPs
        public readonly ?string $ipCountry = null,       // local GeoIP database lookup, if one is installed
        public readonly ?string $devOverride = null,     // ?market_country=GB, honoured only outside production
        public readonly ?string $shippingCountry = null, // the checkout destination: the only signal that decides the order
        public readonly array $dismissed = [],           // markets whose suggestion banner the shopper closed this session
    ) {
    }
}
