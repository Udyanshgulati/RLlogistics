<?php

declare(strict_types=1);

namespace Commerce\Core\Inventory;

/** Base for ledger refusals. getMessage() is written for staff and is safe to show in the dashboard. */
class LedgerException extends \RuntimeException
{
}
