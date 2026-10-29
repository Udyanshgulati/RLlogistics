<?php

declare(strict_types=1);

namespace Commerce\Hub\Controllers;

use Commerce\Core\Db\Database;
use Commerce\Hub\App;
use Commerce\Hub\Http\HttpError;

abstract class Controller
{
    protected readonly Database $db;

    public function __construct(protected readonly App $app)
    {
        $this->db = $app->db;
    }

    protected function actor(): string
    {
        return $this->app->user->actor();
    }

    protected function brandOfOrder(int $orderId): int
    {
        $b = $this->db->value('SELECT s.brand_id FROM orders o JOIN stores s ON s.id = o.store_id WHERE o.id = ?', [$orderId]);
        if ($b === null) {
            throw new HttpError(404, 'This order does not exist.');
        }
        return (int) $b;
    }

    /** Brands an item belongs to: its owner and every brand selling it. */
    protected function brandsOfItem(int $itemId): array
    {
        $owner = $this->db->value('SELECT p.owner_brand_id FROM inventory_items i JOIN products p ON p.id = i.product_id WHERE i.id = ?', [$itemId]);
        if ($owner === null) {
            throw new HttpError(404, 'This product does not exist.');
        }
        $sellers = array_map('intval', array_column($this->db->all("SELECT DISTINCT s.brand_id FROM listings l JOIN stores s ON s.id = l.store_id WHERE l.inventory_item_id = ? AND l.status = 'mapped'", [$itemId]), 'brand_id'));
        return array_values(array_unique([(int) $owner, ...$sellers]));
    }

    /** Requires the permission for at least one of the brands (or all brands). */
    protected function requireForAny(string $permission, array $brandIds): void
    {
        foreach ($brandIds as $b) {
            if ($this->app->user->can($permission, $b)) {
                return;
            }
        }
        $this->app->user->require($permission);
    }

    protected function sqlScope(?array $scope, string $column): string
    {
        if ($scope === null) {
            return '1 = 1';
        }
        return $scope === [] ? '1 = 0' : $column . ' IN (' . implode(',', array_map('intval', $scope)) . ')';
    }
}
