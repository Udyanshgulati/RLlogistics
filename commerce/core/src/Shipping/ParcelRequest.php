<?php

declare(strict_types=1);

namespace Commerce\Core\Shipping;

/** What a rate or booking request needs. Origin is the configured Delhi warehouse; it is never guessed. */
final class ParcelRequest
{
    /** @param list<array{sku: string, qty: int, weight_g: int, value_minor: int, currency: string, hs_code: ?string, origin_country: ?string}> $items */
    public function __construct(
        public readonly array $origin,
        public readonly array $destination,
        public readonly int $weightG,
        public readonly array $dimensionsMm,
        public readonly array $items,
    ) {
    }

    /** Missing data that a carrier will reject, or customs will need. */
    public function problems(): array
    {
        $p = [];
        if (empty($this->origin['postcode']) || empty($this->origin['country'])) {
            $p[] = 'The warehouse address is not configured.';
        }
        if (empty($this->destination['country'])) {
            $p[] = 'The destination country is missing.';
        }
        if ($this->weightG <= 0) {
            $p[] = 'Parcel weight is missing.';
        }
        foreach ($this->items as $i) {
            if (($this->destination['country'] ?? '') !== ($this->origin['country'] ?? '') && (empty($i['hs_code']) || empty($i['origin_country']))) {
                $p[] = "{$i['sku']}: HS code and country of origin are needed for customs.";
            }
        }
        return $p;
    }
}
