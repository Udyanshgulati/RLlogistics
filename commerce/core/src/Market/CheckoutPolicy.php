<?php

declare(strict_types=1);

namespace Commerce\Core\Market;

/**
 * Picks the currency an order is charged in and the payment methods to show.
 *
 * A gateway counts for a currency only if it is enabled AND the currency is in its verified_currencies,
 * which are filled in after the merchant account confirms them. Displaying a currency never makes it
 * chargeable. When the shopper's display currency cannot be charged, the next configured checkout
 * currency is used and the shopper is told before paying.
 */
final class CheckoutPolicy
{
    public function __construct(private readonly MarketConfig $config)
    {
    }

    public function decide(string $marketCode, string $displayCurrency, string $destinationCountry, string $brandCode): CheckoutDecision
    {
        $market = $this->config->market($marketCode);
        if (!$this->config->isMarketEnabledForBrand($marketCode, $brandCode)) {
            return new CheckoutDecision(null, [], 'This store does not currently deliver to your country.');
        }
        $order = array_values(array_unique(array_merge(
            in_array($displayCurrency, $market['checkout_currencies'], true) ? [$displayCurrency] : [],
            $market['checkout_currencies'],
        )));
        foreach ($order as $currency) {
            $gateways = $this->eligibleGateways($currency, $destinationCountry, $brandCode);
            if ($gateways !== []) {
                $notice = $currency === $displayCurrency ? null : sprintf(
                    'Prices are shown in %s, but this order will be charged in %s. The amount in %s is shown before you pay; your card issuer may add a conversion fee.',
                    $displayCurrency, $currency, $currency,
                );
                return new CheckoutDecision($currency, $gateways, $notice);
            }
        }
        return new CheckoutDecision(null, [], 'Online payment is not available for this destination yet. Please contact us.');
    }

    /** @return list<string> */
    public function eligibleGateways(string $currency, string $country, string $brandCode): array
    {
        $out = [];
        foreach ($this->config->gateways() as $id => $g) {
            if (!($g['enabled'] ?? false) || !in_array($currency, $g['verified_currencies'] ?? [], true)) {
                continue;
            }
            $countries = $g['countries'] ?? '*';
            if ($countries !== '*' && !in_array($country, (array) $countries, true)) {
                continue;
            }
            if (in_array($country, $g['exclude_countries'] ?? [], true) || in_array($brandCode, $g['exclude_brands'] ?? [], true)) {
                continue;
            }
            $out[] = $id;
        }
        return $out;
    }
}
