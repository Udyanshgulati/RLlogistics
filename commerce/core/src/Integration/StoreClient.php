<?php

declare(strict_types=1);

namespace Commerce\Core\Integration;

/** What the hub needs from a storefront. One implementation per platform; WooCommerce is the only one today. */
interface StoreClient
{
    /** The order's current storefront status (e.g. pending, processing, cancelled), or null if the order does not exist. */
    public function orderStatus(int $storeId, int $wcOrderId): ?string;

    /** Sets the sellable quantity shown on the storefront for one product or variation. */
    public function setStock(int $storeId, int $wcProductId, int $wcVariationId, int $quantity): void;

    /** Asks the storefront to change an order's status, adding a private note. */
    public function setOrderStatus(int $storeId, int $wcOrderId, string $status, string $note): void;

    /** Adds a shipment note (carrier, tracking) to the order, visible to the customer. */
    public function addShipmentNote(int $storeId, int $wcOrderId, string $note): void;

    /**
     * Asks the storefront to refund through its payment gateway.
     * @return string the storefront's refund id
     */
    public function refund(int $storeId, int $wcOrderId, string $amount, string $reason): string;

    /** Finds an existing refund whose reason contains $marker, so a retry after a lost response never refunds twice. */
    public function findRefund(int $storeId, int $wcOrderId, string $marker): ?string;
}
