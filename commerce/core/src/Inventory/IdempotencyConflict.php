<?php

declare(strict_types=1);

namespace Commerce\Core\Inventory;

/** The same idempotency key was reused with different contents. */
final class IdempotencyConflict extends LedgerException
{
}
