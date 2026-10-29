<?php

declare(strict_types=1);

namespace Commerce\Core\Tests\Support;

use Commerce\Core\Integration\StoreClient;

/** In-memory storefront. failNext makes the next N calls throw like a network outage. */
final class FakeStoreClient implements StoreClient
{
    public array $stock = [];
    public array $statuses = [];
    public array $notes = [];
    public array $refunds = [];
    public int $failNext = 0;
    public bool $loseRefundResponse = false;
    public int $refundCalls = 0;

    public array $orderStatuses = [];

    public function orderStatus(int $storeId, int $wcOrderId): ?string
    {
        $this->maybeFail();
        return $this->orderStatuses["{$storeId}:{$wcOrderId}"] ?? null;
    }

    public function setStock(int $storeId, int $wcProductId, int $wcVariationId, int $quantity): void
    {
        $this->maybeFail();
        $this->stock["{$storeId}:{$wcProductId}:{$wcVariationId}"] = $quantity;
    }

    public function setOrderStatus(int $storeId, int $wcOrderId, string $status, string $note): void
    {
        $this->maybeFail();
        $this->statuses["{$storeId}:{$wcOrderId}"] = $status;
        $this->notes[] = $note;
    }

    public function addShipmentNote(int $storeId, int $wcOrderId, string $note): void
    {
        $this->maybeFail();
        $this->notes[] = $note;
    }

    public function refund(int $storeId, int $wcOrderId, string $amount, string $reason): string
    {
        $this->maybeFail();
        $this->refundCalls++;
        $id = 'r' . (count($this->refunds) + 1);
        $this->refunds[] = ['id' => $id, 'order' => "{$storeId}:{$wcOrderId}", 'amount' => $amount, 'reason' => $reason];
        if ($this->loseRefundResponse) {
            $this->loseRefundResponse = false;
            throw new \RuntimeException('Connection reset after the refund was made');
        }
        return $id;
    }

    public function findRefund(int $storeId, int $wcOrderId, string $marker): ?string
    {
        $this->maybeFail();
        foreach ($this->refunds as $r) {
            if ($r['order'] === "{$storeId}:{$wcOrderId}" && str_contains($r['reason'], $marker)) {
                return $r['id'];
            }
        }
        return null;
    }

    private function maybeFail(): void
    {
        if ($this->failNext > 0) {
            $this->failNext--;
            throw new \RuntimeException('Could not reach the storefront: timed out');
        }
    }
}
