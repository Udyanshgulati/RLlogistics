<?php

declare(strict_types=1);

namespace Commerce\Core\Market;

final class CheckoutDecision
{
    /** @param list<string> $gateways gateway ids that may be shown, in configured order */
    public function __construct(
        public readonly ?string $currency,
        public readonly array $gateways,
        public readonly ?string $notice,
    ) {
    }

    public function canCheckout(): bool
    {
        return $this->currency !== null && $this->gateways !== [];
    }
}
