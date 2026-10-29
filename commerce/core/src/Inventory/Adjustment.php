<?php

declare(strict_types=1);

namespace Commerce\Core\Inventory;

/** The choices on the "Adjust stock" form. */
enum Adjustment: string
{
    case Received = 'received';
    case Damaged = 'damaged';
    case Lost = 'lost';
    case Correction = 'correction';
    case ReturnedToInventory = 'returned_to_inventory';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Stock received',
            self::Damaged => 'Damaged stock',
            self::Lost => 'Lost stock',
            self::Correction => 'Stock correction',
            self::ReturnedToInventory => 'Returned to inventory',
            self::Other => 'Other',
        };
    }

    public function help(): string
    {
        return match ($this) {
            self::Received => 'Adds units to physical stock. Use Purchase orders when the delivery was ordered from a supplier.',
            self::Damaged => 'Moves units out of sellable stock into the damaged hold. They stay counted on hand until written off.',
            self::Lost => 'Removes units that are missing from the shelf.',
            self::Correction => 'Adds or removes units to fix a recording mistake. Enter a negative number to remove.',
            self::ReturnedToInventory => 'Adds back units that were already checked and are fit to sell. Customer returns go through Returns instead.',
            self::Other => 'Adds or removes units for any other reason. Enter a negative number to remove.',
        };
    }

    public function requiresReason(): bool
    {
        return in_array($this, [self::Lost, self::Correction, self::Other], true);
    }

    public function allowsNegative(): bool
    {
        return in_array($this, [self::Correction, self::Other], true);
    }

    public function delta(int $qty): Delta
    {
        return match ($this) {
            self::Received, self::ReturnedToInventory => new Delta(onHand: $qty),
            self::Damaged => new Delta(damaged: $qty),
            self::Lost => new Delta(onHand: -$qty),
            self::Correction, self::Other => new Delta(onHand: $qty),
        };
    }
}
