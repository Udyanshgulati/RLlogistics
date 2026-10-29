<?php

declare(strict_types=1);

namespace Commerce\Core\Integration;

use Commerce\Core\Db\Database;

/**
 * Sends a listing's current available quantity to its storefront. It reads the quantity at send time,
 * so a delayed or retried push always sends the latest number, never a stale one.
 * The storefront quantity is a display copy: checkouts are still checked against the ledger.
 */
final class StockPushHandler implements Handler
{
    public function __construct(private readonly Database $db, private readonly StoreClient $client)
    {
    }

    public function handle(array $payload): void
    {
        $l = $this->db->one(
            "SELECT l.*, COALESCE((SELECT SUM(on_hand - reserved - damaged - quarantine) FROM stock_levels WHERE inventory_item_id = l.inventory_item_id), 0) AS available
             FROM listings l WHERE l.id = ?",
            [$payload['listing_id']],
        );
        if ($l === null) {
            throw new PermanentFailure('The listing no longer exists.');
        }
        // An unlinked listing shows zero, so nothing can be sold that the hub does not track.
        $qty = $l['status'] === 'mapped' ? max(0, (int) $l['available']) : 0;
        $this->client->setStock((int) $l['store_id'], (int) $l['wc_product_id'], (int) $l['wc_variation_id'], $qty);
    }
}
