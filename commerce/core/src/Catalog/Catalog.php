<?php

declare(strict_types=1);

namespace Commerce\Core\Catalog;

use Commerce\Core\Db\Database;
use Commerce\Core\Inventory\Ledger;
use Commerce\Core\Support\Audit;
use Commerce\Core\Support\Clock;

/**
 * Central products, their stock-keeping items, and the explicit link from each storefront listing to an item.
 *
 * Product copy, images and prices stay in each WooCommerce store (the source of truth for content).
 * The hub owns identity (SKU), physical attributes needed for shipping, and stock.
 * Listings are never mapped automatically: an exact SKU match is only offered as a suggestion.
 */
final class Catalog
{
    public function __construct(
        private readonly Database $db,
        private readonly Ledger $ledger,
        private readonly SkuPolicy $policy,
        private readonly Audit $audit,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Creates a product and one stock item per option (e.g. all six apparel sizes), each with zero stock.
     *
     * @param list<string> $options option labels; [] for a product without variations
     * @return array<string, int> SKU => inventory item id
     */
    public function createProduct(int $ownerBrandId, string $code, string $name, ?string $category, array $options, string $actor, string $scheme = 'custom'): array
    {
        $brand = $this->db->one('SELECT * FROM brands WHERE id = ?', [$ownerBrandId]) ?? throw new CatalogException('Unknown brand.');
        $code = SkuPolicy::normalise($code);
        $problems = array_filter([$this->policy->checkRoot($code, $brand['sku_prefix']), trim($name) === '' ? 'Enter a product name.' : null]);
        if ($problems !== []) {
            throw new CatalogException(implode(' ', $problems), array_values($problems));
        }
        $skus = $this->policy->generate($code, $options);
        foreach ($skus as $sku) {
            if ($p = $this->policy->checkSku($sku)) {
                $problems[] = $p;
            }
        }
        $dupes = $this->existingSkus(array_values($skus));
        if ($this->db->value('SELECT 1 FROM products WHERE code = ?', [$code])) {
            $problems[] = "A product with code {$code} already exists.";
        }
        foreach ($dupes as $d) {
            $problems[] = "SKU {$d} is already in use.";
        }
        if ($problems !== []) {
            throw new CatalogException('This product could not be created.', $problems);
        }
        if ($options === []) {
            $scheme = 'none';
        }

        return $this->db->transaction(function () use ($ownerBrandId, $code, $name, $category, $skus, $actor, $scheme) {
            $now = $this->clock->now();
            $productId = $this->db->insert(
                "INSERT INTO products (code, name, category, owner_brand_id, variant_scheme, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'draft', ?, ?)",
                [$code, trim($name), $category, $ownerBrandId, $scheme, $now, $now],
            );
            $ids = [];
            foreach ($skus as $label => $sku) {
                $ids[$sku] = $this->insertItem($productId, $sku, $label === '' ? null : (string) $label, $now);
            }
            $this->audit->log($actor, 'product.create', 'product', $productId, ['code' => $code, 'skus' => array_keys($ids)]);
            return $ids;
        });
    }

    /** Adds one option (e.g. a size added later) to an existing product. */
    public function addOption(int $productId, string $label, string $actor): int
    {
        $p = $this->db->one('SELECT * FROM products WHERE id = ?', [$productId]) ?? throw new CatalogException('Unknown product.');
        $sku = $this->policy->optionSku($p['code'], $label);
        if ($this->existingSkus([$sku]) !== []) {
            throw new CatalogException("SKU {$sku} is already in use.", ["SKU {$sku} is already in use."]);
        }
        return $this->db->transaction(function () use ($productId, $sku, $label, $actor) {
            $id = $this->insertItem($productId, $sku, strtoupper(trim($label)), $this->clock->now());
            $this->audit->log($actor, 'product.add_option', 'product', $productId, ['sku' => $sku]);
            return $id;
        });
    }

    /** Records a product/variation seen on a storefront. Existing mappings are left alone. */
    public function recordListing(int $storeId, int $wcProductId, int $wcVariationId, ?string $wcSku, ?string $wcName): int
    {
        $this->db->run(
            "INSERT INTO listings (store_id, wc_product_id, wc_variation_id, wc_sku, wc_name, status) VALUES (?, ?, ?, ?, ?, 'unmapped')
             ON DUPLICATE KEY UPDATE wc_sku = VALUES(wc_sku), wc_name = VALUES(wc_name)",
            [$storeId, $wcProductId, $wcVariationId, $wcSku === null ? null : SkuPolicy::normalise($wcSku), $wcName],
        );
        return (int) $this->db->value('SELECT id FROM listings WHERE store_id = ? AND wc_product_id = ? AND wc_variation_id = ?', [$storeId, $wcProductId, $wcVariationId]);
    }

    /**
     * Links a storefront listing to a central stock item. Two cases need an explicit "yes" from the user,
     * because they decide whether stores share physical stock:
     *   - the store belongs to a different brand than the item's owner (shared inventory), and
     *   - the storefront SKU differs from the central SKU.
     * Re-pointing an already mapped listing requires unmapping it first.
     */
    public function mapListing(int $listingId, int $itemId, string $actor, bool $confirmShared = false, bool $confirmSkuMismatch = false): void
    {
        $this->db->transaction(function () use ($listingId, $itemId, $actor, $confirmShared, $confirmSkuMismatch) {
            $l = $this->db->one('SELECT l.*, s.brand_id AS store_brand FROM listings l JOIN stores s ON s.id = l.store_id WHERE l.id = ? FOR UPDATE', [$listingId])
                ?? throw new CatalogException('Unknown storefront listing.');
            $item = $this->db->one('SELECT i.*, p.owner_brand_id FROM inventory_items i JOIN products p ON p.id = i.product_id WHERE i.id = ?', [$itemId])
                ?? throw new CatalogException('Unknown stock item.');
            if ($l['status'] === 'mapped') {
                if ((int) $l['inventory_item_id'] === $itemId) {
                    return;
                }
                throw new CatalogException('This listing is already linked to another SKU. Unlink it first, then link it again.');
            }
            if ((int) $l['store_brand'] !== (int) $item['owner_brand_id'] && !$confirmShared) {
                throw new CatalogException("This store will sell stock owned by another brand. Orders here will draw from the same units as {$item['sku']}.", needsConfirmation: 'shared');
            }
            if ($l['wc_sku'] !== null && $l['wc_sku'] !== $item['sku'] && !$confirmSkuMismatch) {
                throw new CatalogException("The storefront SKU {$l['wc_sku']} is different from the central SKU {$item['sku']}.", needsConfirmation: 'sku_mismatch');
            }
            $other = $this->db->value("SELECT id FROM listings WHERE store_id = ? AND inventory_item_id = ? AND id <> ?", [$l['store_id'], $itemId, $listingId]);
            if ($other) {
                throw new CatalogException('Another listing in this store is already linked to this SKU.');
            }
            $this->db->run("UPDATE listings SET inventory_item_id = ?, status = 'mapped', mapped_by = ?, mapped_at = ? WHERE id = ?", [$itemId, $actor, $this->clock->now(), $listingId]);
            $this->audit->log($actor, 'listing.map', 'listing', $listingId, ['sku' => $item['sku'], 'shared' => (int) $l['store_brand'] !== (int) $item['owner_brand_id']]);
            // The store gets the central quantity right away.
            $now = $this->clock->now();
            $this->db->run(
                "INSERT INTO outbox (topic, dedupe_key, payload, status, next_attempt_at, created_at, updated_at) VALUES ('stock.push', ?, ?, 'pending', ?, ?, ?) ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)",
                ['stock.push:listing:' . $listingId, json_encode(['listing_id' => $listingId]), $now, $now, $now],
            );
        });
    }

    public function unmapListing(int $listingId, string $actor, string $reason): void
    {
        if (trim($reason) === '') {
            throw new CatalogException('Add a reason for unlinking.');
        }
        $this->db->transaction(function () use ($listingId, $actor, $reason) {
            $l = $this->db->one('SELECT * FROM listings WHERE id = ? FOR UPDATE', [$listingId]) ?? throw new CatalogException('Unknown storefront listing.');
            if ($l['status'] !== 'mapped') {
                return;
            }
            $this->db->run("UPDATE listings SET inventory_item_id = NULL, status = 'unmapped', mapped_by = NULL, mapped_at = NULL WHERE id = ?", [$listingId]);
            $this->audit->log($actor, 'listing.unmap', 'listing', $listingId, ['previous_item' => (int) $l['inventory_item_id'], 'reason' => $reason]);
            // The storefront drops to zero so nothing untracked can be sold.
            $now = $this->clock->now();
            $this->db->run(
                "INSERT INTO outbox (topic, dedupe_key, payload, status, next_attempt_at, created_at, updated_at) VALUES ('stock.push', ?, ?, 'pending', ?, ?, ?) ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)",
                ['stock.push:listing:' . $listingId, json_encode(['listing_id' => $listingId]), $now, $now, $now],
            );
        });
    }

    /** Unmapped listings whose storefront SKU exactly equals a central SKU. Suggestions only: a person confirms each one. */
    public function suggestions(?int $storeId = null): array
    {
        return $this->db->all(
            "SELECT l.id AS listing_id, l.store_id, l.wc_sku, l.wc_name, i.id AS item_id, i.sku
             FROM listings l JOIN inventory_items i ON i.sku = l.wc_sku
             WHERE l.status = 'unmapped'" . ($storeId ? ' AND l.store_id = ' . (int) $storeId : '') . ' ORDER BY l.id',
        );
    }

    /** 'shared' (sold by more than one brand), 'exclusive' (one brand) or 'unmapped' (no storefront sells it yet). */
    public function sharing(int $itemId): string
    {
        $brands = (int) $this->db->value(
            "SELECT COUNT(DISTINCT s.brand_id) FROM listings l JOIN stores s ON s.id = l.store_id WHERE l.inventory_item_id = ? AND l.status = 'mapped'",
            [$itemId],
        );
        return match (true) {
            $brands === 0 => 'unmapped',
            $brands === 1 => 'exclusive',
            default => 'shared',
        };
    }

    /** @param list<string> $skus @return list<string> those already taken */
    private function existingSkus(array $skus): array
    {
        if ($skus === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($skus), '?'));
        return array_column($this->db->all("SELECT sku FROM inventory_items WHERE sku IN ({$in})", $skus), 'sku');
    }

    private function insertItem(int $productId, string $sku, ?string $label, \DateTimeImmutable $now): int
    {
        $id = $this->db->insert(
            'INSERT INTO inventory_items (product_id, sku, option_label, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            [$productId, $sku, $label, $now, $now],
        );
        $this->ledger->open($id, 0, 'system');
        return $id;
    }
}
