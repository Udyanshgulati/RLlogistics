<?php

declare(strict_types=1);

namespace Commerce\Core\Inventory;

use Commerce\Core\Db\Database;

/**
 * Read model behind the Inventory table, the Overview counts and the CSV export, so all three agree.
 *
 * Status policy: Out of stock when available <= 0; Low stock when available is below the reorder level;
 * otherwise In stock. Incoming (open purchase order quantity) is shown but never counted as available.
 */
final class InventoryView
{
    public const SORTS = ['available' => 'available', 'name' => 'p.name', 'sku' => 'i.sku'];

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param array{q?: string, brand?: int|null, category?: string, size?: string, status?: string, sort?: string, dir?: string} $f
     * @param list<int>|null $brandScope brands the viewer may see; null = all
     * @return array{rows: list<array>, total: int}
     */
    public function list(array $f = [], ?array $brandScope = null, int $limit = 50, int $offset = 0): array
    {
        [$where, $params] = $this->filters($f, $brandScope);
        $sort = self::SORTS[$f['sort'] ?? 'name'] ?? 'p.name';
        $dir = strtolower($f['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
        $base = $this->baseQuery();
        $having = $this->statusHaving($f['status'] ?? '');
        $total = (int) $this->db->value("SELECT COUNT(*) FROM ({$base} WHERE {$where} GROUP BY i.id {$having}) t", $params);
        $rows = $this->db->all("{$base} WHERE {$where} GROUP BY i.id {$having} ORDER BY {$sort} {$dir}, i.sku LIMIT " . max(1, $limit) . ' OFFSET ' . max(0, $offset), $params);
        foreach ($rows as &$r) {
            $r = self::decorate($r);
        }
        return ['rows' => $rows, 'total' => $total];
    }

    public function one(int $itemId): ?array
    {
        $row = $this->db->one($this->baseQuery() . ' WHERE i.id = ? GROUP BY i.id', [$itemId]);
        return $row === null ? null : self::decorate($row);
    }

    /** Counts for the Overview cards. */
    public function totals(?int $brandId = null, ?array $brandScope = null): array
    {
        [$where, $params] = $this->filters(['brand' => $brandId], $brandScope);
        $t = $this->db->one(
            "SELECT COUNT(*) skus, COALESCE(SUM(on_hand), 0) on_hand, COALESCE(SUM(available), 0) available,
                    SUM(available <= 0) out_of_stock, SUM(available > 0 AND available < reorder_level) low_stock
             FROM ({$this->baseQuery()} WHERE {$where} AND i.status = 'active' GROUP BY i.id) t",
            $params,
        );
        return array_map('intval', $t);
    }

    /** @return list<array> movements newest first, for the item history panel */
    public function history(int $itemId, int $limit = 50): array
    {
        return $this->db->all('SELECT * FROM stock_movements WHERE inventory_item_id = ? ORDER BY id DESC LIMIT ' . max(1, $limit), [$itemId]);
    }

    private function baseQuery(): string
    {
        // Listing brands are aggregated in a subquery so the stock sums are not multiplied by the join.
        return "SELECT i.id, i.sku, i.option_label, i.reorder_level, i.status AS item_status, i.barcode,
                       p.id AS product_id, p.code AS product_code, p.name, p.category, p.image_url, p.owner_brand_id,
                       COALESCE(SUM(l.on_hand), 0) on_hand, COALESCE(SUM(l.reserved), 0) reserved,
                       COALESCE(SUM(l.damaged), 0) damaged, COALESCE(SUM(l.quarantine), 0) quarantine,
                       COALESCE(SUM(l.on_hand - l.reserved - l.damaged - l.quarantine), 0) available,
                       (SELECT COALESCE(SUM(pol.qty_ordered - pol.qty_received - pol.qty_missing), 0)
                          FROM purchase_order_lines pol JOIN purchase_orders po ON po.id = pol.purchase_order_id
                         WHERE pol.inventory_item_id = i.id AND po.status IN ('ordered','partially_received')) incoming,
                       (SELECT GROUP_CONCAT(DISTINCT s.brand_id ORDER BY s.brand_id)
                          FROM listings li JOIN stores s ON s.id = li.store_id
                         WHERE li.inventory_item_id = i.id AND li.status = 'mapped') listing_brands
                FROM inventory_items i
                JOIN products p ON p.id = i.product_id
                LEFT JOIN stock_levels l ON l.inventory_item_id = i.id";
    }

    private function filters(array $f, ?array $brandScope): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (($q = trim((string) ($f['q'] ?? ''))) !== '') {
            $where[] = '(i.sku LIKE ? OR p.name LIKE ? OR i.barcode = ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $q);
        }
        // A brand sees items it owns or sells.
        $brandSql = '(p.owner_brand_id IN (%1$s) OR EXISTS (SELECT 1 FROM listings lb JOIN stores sb ON sb.id = lb.store_id WHERE lb.inventory_item_id = i.id AND lb.status = \'mapped\' AND sb.brand_id IN (%1$s)))';
        if (!empty($f['brand'])) {
            $where[] = sprintf($brandSql, '?');
            array_push($params, (int) $f['brand'], (int) $f['brand']);
        }
        if ($brandScope !== null) {
            $ids = implode(',', array_map('intval', $brandScope ?: [0]));
            $where[] = sprintf($brandSql, $ids);
        }
        if (($c = trim((string) ($f['category'] ?? ''))) !== '') {
            $where[] = 'p.category = ?';
            $params[] = $c;
        }
        if (($s = trim((string) ($f['size'] ?? ''))) !== '') {
            $where[] = 'i.option_label = ?';
            $params[] = $s;
        }
        return [implode(' AND ', $where), $params];
    }

    private function statusHaving(string $status): string
    {
        return match ($status) {
            'out' => 'HAVING available <= 0',
            'low' => 'HAVING available > 0 AND available < reorder_level',
            'in' => 'HAVING available > 0 AND available >= reorder_level',
            default => '',
        };
    }

    private static function decorate(array $r): array
    {
        foreach (['on_hand', 'reserved', 'damaged', 'quarantine', 'available', 'incoming', 'reorder_level'] as $k) {
            $r[$k] = (int) $r[$k];
        }
        $r['status'] = $r['available'] <= 0 ? 'out' : ($r['available'] < $r['reorder_level'] ? 'low' : 'in');
        $brands = $r['listing_brands'] === null ? [] : array_map('intval', explode(',', (string) $r['listing_brands']));
        $r['listing_brands'] = $brands;
        $r['sharing'] = match (count($brands)) {
            0 => 'unmapped',
            1 => 'exclusive',
            default => 'shared',
        };
        return $r;
    }
}
