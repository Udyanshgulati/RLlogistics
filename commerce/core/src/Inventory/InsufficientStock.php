<?php

declare(strict_types=1);

namespace Commerce\Core\Inventory;

final class InsufficientStock extends LedgerException
{
    /** @param array<int, array{requested: int, available: int}> $shortfalls keyed by inventory item id */
    public function __construct(public readonly array $shortfalls)
    {
        $parts = [];
        foreach ($shortfalls as $item => $s) {
            $parts[] = sprintf('item %d: %d requested, %d available', $item, $s['requested'], $s['available']);
        }
        parent::__construct('Not enough available stock (' . implode('; ', $parts) . ').');
    }
}
