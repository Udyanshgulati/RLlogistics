<?php

declare(strict_types=1);

namespace Commerce\Core\Market;

/**
 * Decides which market a shopper sees. Country-level only; no GPS, no city.
 *
 * Browsing precedence: this session's choice > development override (non-production only) > saved
 * preference > CDN country > GeoIP country > default market. An explicit choice is never replaced by
 * an inferred one; at most a dismissible suggestion is shown, and not again once dismissed.
 *
 * At checkout the shipping country decides the market, whatever was browsed. Geolocation is a
 * convenience, not proof of where the customer lives or may buy.
 */
final class CountryResolver
{
    public function __construct(private readonly MarketConfig $config, private readonly bool $allowDevOverride = false)
    {
    }

    public function browsing(Signals $s): Resolution
    {
        $inferred = CountryCode::clean($s->cdnCountry) ?? CountryCode::clean($s->ipCountry);
        $inferredSource = CountryCode::clean($s->cdnCountry) !== null ? 'cdn' : 'ip';

        $candidates = [
            'session' => $s->sessionChoice,
            'dev_override' => $this->allowDevOverride ? $s->devOverride : null,
            'saved' => $s->savedPreference,
        ];
        foreach ($candidates as $source => $country) {
            $country = CountryCode::clean($country);
            if ($country === null) {
                continue;
            }
            $market = $this->config->marketFor($country);
            $suggest = null;
            if ($source !== 'dev_override' && $inferred !== null) {
                $im = $this->config->marketFor($inferred);
                if ($im !== $market && !in_array($im, $s->dismissed, true)) {
                    $suggest = $inferred;
                }
            }
            return new Resolution($country, $market, $source, $suggest, $suggest === null ? null : $this->config->marketFor($suggest));
        }
        if ($inferred !== null) {
            return new Resolution($inferred, $this->config->marketFor($inferred), $inferredSource);
        }
        return new Resolution(null, $this->config->defaultMarket(), 'default', showSelector: true);
    }

    /** The market an order is priced, taxed and shipped under: always the destination's. */
    public function checkout(Signals $s): Resolution
    {
        $browsing = $this->browsing($s);
        $dest = CountryCode::clean($s->shippingCountry);
        if ($dest === null) {
            return $browsing;
        }
        $market = $this->config->marketFor($dest);
        return new Resolution($dest, $market, 'shipping', repriceRequired: $market !== $browsing->market);
    }
}
