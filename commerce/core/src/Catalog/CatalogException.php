<?php

declare(strict_types=1);

namespace Commerce\Core\Catalog;

/** Refusals written for staff; safe to show in the dashboard. */
class CatalogException extends \RuntimeException
{
    /** @param list<string> $problems */
    public function __construct(string $message, public readonly array $problems = [], public readonly ?string $needsConfirmation = null)
    {
        parent::__construct($message);
    }
}
