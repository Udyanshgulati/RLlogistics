<?php

declare(strict_types=1);

namespace Commerce\Core\Market;

use Commerce\Core\Db\Database;

/**
 * The price a shopper sees in their display currency.
 *   market_price – a price the brand set for that currency (stable; ignores exchange rates)
 *   converted    – the base price converted at the stored rate (labelled approximate when charged in another currency)
 *   base         – no market price and no fresh rate: shown in the base currency
 */
final class PriceResolver
{
    /** @param string $rounding none | whole (round converted prices up to a whole unit) */
    public function __construct(private readonly Database $db, private readonly ExchangeRates $rates, private readonly string $baseCurrency, private readonly string $rounding = 'none')
    {
    }

    /** @return array{minor: int, currency: string, kind: string, approximate: bool, rate: ?string, as_of: ?string} */
    public function price(int $storeId, int $wcProductId, int $wcVariationId, int $baseMinor, string $displayCurrency, string $checkoutCurrency): array
    {
        $mp = $this->db->one(
            'SELECT amount_minor FROM market_prices WHERE store_id = ? AND wc_product_id = ? AND wc_variation_id = ? AND currency = ?',
            [$storeId, $wcProductId, $wcVariationId, $displayCurrency],
        );
        if ($mp !== null) {
            return ['minor' => (int) $mp['amount_minor'], 'currency' => $displayCurrency, 'kind' => 'market_price', 'approximate' => $displayCurrency !== $checkoutCurrency, 'rate' => null, 'as_of' => null];
        }
        if ($displayCurrency === $this->baseCurrency) {
            return ['minor' => $baseMinor, 'currency' => $this->baseCurrency, 'kind' => 'base', 'approximate' => false, 'rate' => null, 'as_of' => null];
        }
        $c = $this->rates->convert($baseMinor, $this->baseCurrency, $displayCurrency);
        if ($c === null) {
            return ['minor' => $baseMinor, 'currency' => $this->baseCurrency, 'kind' => 'base', 'approximate' => false, 'rate' => null, 'as_of' => null];
        }
        $minor = $c['minor'];
        if ($this->rounding === 'whole') {
            $unit = 10 ** Currency::exponent($displayCurrency);
            $minor = (int) (ceil($minor / $unit) * $unit);
        }
        return ['minor' => $minor, 'currency' => $displayCurrency, 'kind' => 'converted', 'approximate' => $displayCurrency !== $checkoutCurrency, 'rate' => $c['rate'], 'as_of' => $c['as_of']];
    }
}
