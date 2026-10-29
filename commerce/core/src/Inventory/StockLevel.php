<?php

declare(strict_types=1);

namespace Commerce\Core\Inventory;

/** Balances for one item in one warehouse. Available = on hand - reserved - damaged - awaiting inspection. */
final class StockLevel
{
    public function __construct(
        public readonly int $itemId,
        public readonly int $warehouseId,
        public readonly int $onHand,
        public readonly int $reserved,
        public readonly int $damaged,
        public readonly int $quarantine,
    ) {
    }

    public static function fromRow(array $r): self
    {
        return new self((int) $r['inventory_item_id'], (int) $r['warehouse_id'], (int) $r['on_hand'], (int) $r['reserved'], (int) $r['damaged'], (int) $r['quarantine']);
    }

    public function available(): int
    {
        return $this->onHand - $this->reserved - $this->damaged - $this->quarantine;
    }

    public function with(Delta $d): self
    {
        return new self($this->itemId, $this->warehouseId, $this->onHand + $d->onHand, $this->reserved + $d->reserved, $this->damaged + $d->damaged, $this->quarantine + $d->quarantine);
    }

    public function isValid(): bool
    {
        return min($this->onHand, $this->reserved, $this->damaged, $this->quarantine) >= 0 && $this->available() >= 0;
    }
}
