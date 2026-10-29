<?php

declare(strict_types=1);

namespace Commerce\Core\Shipping;

/** One service offer exactly as a carrier API returned it, mapped to our fields. */
final class Quote
{
    public function __construct(
        public readonly string $carrier,
        public readonly string $serviceCode,
        public readonly string $serviceName,
        /** express | standard | economy, set by the adapter's mapping of the carrier's product codes */
        public readonly string $level,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly ?\DateTimeImmutable $estimatedDelivery = null,
        /** true only when the carrier's response marks the date as a guaranteed commitment */
        public readonly bool $carrierGuaranteed = false,
        public readonly array $surcharges = [],
    ) {
    }
}
