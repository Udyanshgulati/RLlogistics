<?php

declare(strict_types=1);

namespace Commerce\Core\Market;

/**
 * Whether a product may be offered to a destination. This never reads or changes stock: a product can be
 * in stock and still not sellable to a country, and a destination rule never creates a separate stock pool.
 */
final class Availability
{
    public function __construct(private readonly MarketConfig $config)
    {
    }

    /**
     * @param array{allowed_countries?: list<string>, blocked_countries?: list<string>} $productRules from the product's shipping attributes
     * @return array{available: bool, reason: ?string}
     */
    public function check(string $brandCode, string $country, array $productRules = []): array
    {
        $market = $this->config->marketFor($country);
        if (!$this->config->isMarketEnabledForBrand($market, $brandCode)) {
            return ['available' => false, 'reason' => 'This store does not currently deliver to your country.'];
        }
        $allow = $this->config->shipping()['destination_allowlist'];
        if ($allow !== [] && !in_array($country, $allow, true)) {
            return ['available' => false, 'reason' => 'We do not ship to this country yet.'];
        }
        if (in_array($country, $productRules['blocked_countries'] ?? [], true)
            || (isset($productRules['allowed_countries']) && !in_array($country, $productRules['allowed_countries'], true))) {
            return ['available' => false, 'reason' => 'This item cannot be shipped to your country.'];
        }
        return ['available' => true, 'reason' => null];
    }
}
