<?php

declare(strict_types=1);

namespace Commerce\Core\Inventory;

final class ReservationResult
{
    /**
     * @param bool $ok false when the ledger refused or needs a person (see $exception)
     * @param bool $replayed true when the call repeated an earlier one and changed nothing
     */
    public function __construct(
        public readonly int $reservationId,
        public readonly string $state,
        public readonly bool $ok = true,
        public readonly bool $replayed = false,
        public readonly ?string $exception = null,
    ) {
    }
}
