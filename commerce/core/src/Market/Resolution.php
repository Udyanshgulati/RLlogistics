<?php

declare(strict_types=1);

namespace Commerce\Core\Market;

final class Resolution
{
    public function __construct(
        public readonly ?string $country,
        public readonly string $market,
        /** session | dev_override | saved | cdn | ip | default | shipping */
        public readonly string $source,
        /** a gentle banner: "Shopping from the United States? View prices in USD" */
        public readonly ?string $suggestCountry = null,
        public readonly ?string $suggestMarket = null,
        /** no country could be inferred: show the selector prominently */
        public readonly bool $showSelector = false,
        /** checkout destination is in a different market from the one browsed: reprice and tell the shopper */
        public readonly bool $repriceRequired = false,
    ) {
    }
}
