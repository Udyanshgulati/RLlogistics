<?php

declare(strict_types=1);

namespace Commerce\Core\Inventory;

final class Delta
{
    public function __construct(
        public readonly int $onHand = 0,
        public readonly int $reserved = 0,
        public readonly int $damaged = 0,
        public readonly int $quarantine = 0,
    ) {
    }

    public function availableChange(): int
    {
        return $this->onHand - $this->reserved - $this->damaged - $this->quarantine;
    }
}
