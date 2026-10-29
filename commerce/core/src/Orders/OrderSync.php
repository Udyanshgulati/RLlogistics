<?php

declare(strict_types=1);

namespace Commerce\Core\Orders;

use Commerce\Core\Db\Database;
use Commerce\Core\Inventory\InsufficientStock;
use Commerce\Core\Inventory\Ledger;
use Commerce\Core\Market\Currency;
use Commerce\Core\Support\Clock;

/**
 * Applies order snapshots (the WooCommerce REST v3 order representation, as sent by WooCommerce webhooks)
 * to the central order record and the stock ledger.
 *
 * Safe to receive the same event twice (inbox dedupe on event key), events out of order (older
 * date_modified_gmt is ignored) and a snapshot for an order whose checkout reservation call never
 * reached the hub (it reserves then, or flags the order if the stock is gone).
 *
 * Storefront status → ledger:
 *   pending / checkout-draft  nothing (the checkout hold is already in place)
 *   on-hold                    extend the hold (payment awaiting confirmation)
 *   processing / completed     confirm (paid)
 *   failed / cancelled         release
 *   refunded                   release anything not yet dispatched; dispatched units come back through Returns
 */
final class OrderSync
{
    public const PAID = ['processing', 'completed'];

    public function __construct(
        private readonly Database $db,
        private readonly Ledger $ledger,
        private readonly Clock $clock,
        private readonly int $holdSeconds = 1800,
        private readonly int $hardHoldSeconds = 172800,
        private readonly int $onHoldExtendSeconds = 86400,
    ) {
    }

    /** @return string 'applied' | 'duplicate' | 'stale' */
    public function ingest(int $storeId, string $eventKey, array $o): string
    {
        $source = 'store:' . $storeId;
        $now = $this->clock->now();
        try {
            $inboxId = $this->db->insert(
                'INSERT INTO inbox_events (source, event_key, topic, payload, status, received_at) VALUES (?, ?, ?, ?, \'received\', ?)',
                [$source, $eventKey, 'order.snapshot', json_encode(self::redactForInbox($o)), $now],
            );
        } catch (\PDOException $e) {
            if (Database::isDuplicateKey($e)) {
                return 'duplicate';
            }
            throw $e;
        }

        try {
            $result = $this->db->transaction(fn () => $this->applySnapshot($storeId, $o));
            $this->db->run("UPDATE inbox_events SET status = ?, attempts = attempts + 1, processed_at = ? WHERE id = ?", [$result === 'stale' ? 'ignored' : 'processed', $this->clock->now(), $inboxId]);
            return $result;
        } catch (\Throwable $e) {
            $this->db->run("UPDATE inbox_events SET status = 'failed', attempts = attempts + 1, last_error = ? WHERE id = ?", [mb_substr($e->getMessage(), 0, 1000), $inboxId]);
            throw $e;
        }
    }

    /** Re-runs a failed inbox event (Integration health → Retry). */
    public function retry(int $inboxId): string
    {
        $row = $this->db->one("SELECT * FROM inbox_events WHERE id = ? AND status = 'failed'", [$inboxId]) ?? throw new \RuntimeException('Nothing to retry.');
        $storeId = (int) substr($row['source'], strlen('store:'));
        try {
            $result = $this->db->transaction(fn () => $this->applySnapshot($storeId, json_decode($row['payload'], true)));
        } catch (\Throwable $e) {
            $this->db->run('UPDATE inbox_events SET attempts = attempts + 1, last_error = ? WHERE id = ?', [mb_substr($e->getMessage(), 0, 1000), $inboxId]);
            throw $e;
        }
        $this->db->run("UPDATE inbox_events SET status = 'processed', attempts = attempts + 1, last_error = NULL, processed_at = ? WHERE id = ?", [$this->clock->now(), $inboxId]);
        return $result;
    }

    /**
     * "Check again" after staff fixed the cause (linked the product, restocked): re-reads the links and,
     * for a paid order, reserves its stock. Clears the problem only when that now succeeds.
     * @return string|null the remaining attention code, or null when resolved
     */
    public function recheck(int $orderId, string $actor): ?string
    {
        return $this->db->transaction(function () use ($orderId, $actor) {
            $o = $this->db->one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]) ?? throw new \RuntimeException('Unknown order.');
            if (!in_array($o['attention'], ['unmapped_item', 'paid_no_stock'], true)) {
                return $o['attention'];
            }
            $this->db->run(
                "UPDATE order_lines ol
                 LEFT JOIN listings l ON l.store_id = ? AND l.wc_product_id = ol.wc_product_id AND l.wc_variation_id = ol.wc_variation_id AND l.status = 'mapped'
                 LEFT JOIN inventory_items i ON i.id = l.inventory_item_id
                 SET ol.listing_id = l.id, ol.inventory_item_id = l.inventory_item_id, ol.option_label = i.option_label
                 WHERE ol.order_id = ?",
                [$o['store_id'], $orderId],
            );
            $lines = $this->itemQuantities($orderId);
            $attention = null;
            if ($lines === null) {
                $attention = 'unmapped_item';
            } elseif ($o['payment_status'] === 'paid') {
                $key = "store:{$o['store_id']}:order:{$o['wc_order_id']}";
                $existing = $this->ledger->reservationByKey($key);
                try {
                    if ($existing === null) {
                        $r = $this->ledger->reserve((int) $o['store_id'], (string) $o['wc_order_id'], $lines, $key, $this->holdSeconds, $this->hardHoldSeconds);
                        $this->ledger->confirm($r->reservationId, $o['transaction_ref'], $actor);
                        $this->db->run('UPDATE orders SET reservation_id = ? WHERE id = ?', [$r->reservationId, $orderId]);
                    } else {
                        $res = $this->ledger->confirm((int) $existing['id'], $o['transaction_ref'], $actor);
                        $attention = $res->ok ? null : ($res->exception === 'paid_after_release' ? 'paid_after_cancel' : 'paid_no_stock');
                    }
                } catch (InsufficientStock) {
                    $attention = 'paid_no_stock';
                }
            }
            $this->db->run('UPDATE orders SET attention = ?, updated_at = ? WHERE id = ?', [$attention, $this->clock->now(), $orderId]);
            $this->event($orderId, $attention === null ? 'attention.resolved' : 'attention.recheck', $attention === null ? 'Problem resolved: stock reserved' : 'Checked again: the problem is still there', $actor);
            return $attention;
        });
    }

    private function applySnapshot(int $storeId, array $o): string
    {
        $now = $this->clock->now();
        $currency = strtoupper((string) $o['currency']);
        $modified = self::utc($o['date_modified_gmt'] ?? $o['date_created_gmt']);
        $existing = $this->db->one('SELECT * FROM orders WHERE store_id = ? AND wc_order_id = ? FOR UPDATE', [$storeId, (int) $o['id']]);
        if ($existing !== null && new \DateTimeImmutable($existing['source_modified_at'], new \DateTimeZone('UTC')) > $modified) {
            return 'stale';
        }

        $refunded = 0;
        foreach ($o['refunds'] ?? [] as $r) {
            $refunded += abs(Currency::toMinor(ltrim((string) $r['total'], '-'), $currency));
        }
        $total = Currency::toMinor((string) $o['total'], $currency);
        $status = (string) $o['status'];
        $payment = match (true) {
            $status === 'refunded' || ($refunded > 0 && $refunded >= $total) => 'refunded',
            $refunded > 0 => 'partially_refunded',
            in_array($status, self::PAID, true) => 'paid',
            $status === 'failed' => 'failed',
            $status === 'cancelled' => 'cancelled',
            default => 'pending',
        };
        $ship = $o['shipping'] ?? [];
        $bill = $o['billing'] ?? [];
        $meta = self::meta($o);
        $fields = [
            'order_number' => (string) ($o['number'] ?? $o['id']),
            'placed_at' => self::utc($o['date_created_gmt']),
            'wc_status' => $status,
            'payment_status' => $payment,
            'currency' => $currency,
            'subtotal_minor' => array_sum(array_map(fn ($l) => Currency::toMinor((string) ($l['subtotal'] ?? $l['total']), $currency), $o['line_items'] ?? [])),
            'discount_minor' => Currency::toMinor((string) ($o['discount_total'] ?? '0'), $currency),
            'shipping_minor' => Currency::toMinor((string) ($o['shipping_total'] ?? '0'), $currency),
            'tax_minor' => Currency::toMinor((string) ($o['total_tax'] ?? '0'), $currency),
            'total_minor' => $total,
            'refunded_minor' => $refunded,
            'payment_gateway' => $o['payment_method'] ?? null,
            'transaction_ref' => ($o['transaction_id'] ?? '') ?: null,
            'display_currency' => $meta['commerce_display_currency'] ?? null,
            'market_code' => $meta['commerce_market'] ?? null,
            'customer_name' => trim(($ship['first_name'] ?? $bill['first_name'] ?? '') . ' ' . ($ship['last_name'] ?? $bill['last_name'] ?? '')) ?: null,
            'customer_email' => $bill['email'] ?? null,
            'customer_phone' => ($ship['phone'] ?? '') ?: ($bill['phone'] ?? null),
            'ship_country' => isset($ship['country']) && $ship['country'] !== '' ? strtoupper($ship['country']) : null,
            'shipping_address' => json_encode(self::address($ship)),
            'billing_address' => json_encode(self::address($bill)),
            'shipping_service_requested' => $o['shipping_lines'][0]['method_title'] ?? null,
            'source_modified_at' => $modified,
            'updated_at' => $now,
        ];

        if ($existing === null) {
            $cols = array_keys($fields);
            $orderId = $this->db->insert(
                'INSERT INTO orders (store_id, wc_order_id, created_at, ' . implode(', ', $cols) . ') VALUES (?, ?, ?' . str_repeat(', ?', count($cols)) . ')',
                array_merge([$storeId, (int) $o['id'], $now], array_values($fields)),
            );
            $this->event($orderId, 'received', 'Order received from the storefront', 'store:' . $storeId);
        } else {
            $orderId = (int) $existing['id'];
            $set = implode(', ', array_map(fn ($c) => "{$c} = ?", array_keys($fields)));
            $this->db->run("UPDATE orders SET {$set} WHERE id = ?", array_merge(array_values($fields), [$orderId]));
        }

        $unmapped = $this->syncLines($orderId, $storeId, $o['line_items'] ?? [], $currency);
        $previousPayment = $existing['payment_status'] ?? null;
        $attention = $unmapped ? 'unmapped_item' : null;
        $attention = $this->driveLedger($orderId, $storeId, $o, $status, $previousPayment) ?? $attention;

        if (in_array($status, ['cancelled', 'failed'], true)) {
            $this->db->run("UPDATE orders SET fulfilment_status = IF(fulfilment_status IN ('unfulfilled','processing','picked','packed'), 'cancelled', fulfilment_status) WHERE id = ?", [$orderId]);
        }
        $this->db->run('UPDATE orders SET attention = ? WHERE id = ?', [$attention, $orderId]);

        if ($previousPayment !== $payment) {
            $label = ['paid' => 'Payment confirmed', 'failed' => 'Payment failed', 'cancelled' => 'Order cancelled', 'refunded' => 'Order refunded', 'partially_refunded' => 'Partial refund recorded', 'pending' => 'Awaiting payment'][$payment];
            if ($existing !== null || $payment !== 'pending') {
                $this->event($orderId, 'payment.' . $payment, $label, 'store:' . $storeId);
            }
            $this->notify($orderId, $payment === 'pending' ? 'order.placed' : 'order.' . $payment);
        }
        return 'applied';
    }

    /** @return bool true if any line has no mapped stock item */
    private function syncLines(int $orderId, int $storeId, array $lines, string $currency): bool
    {
        $unmapped = false;
        foreach ($lines as $l) {
            $listing = $this->db->one(
                "SELECT l.id, l.inventory_item_id, i.option_label FROM listings l LEFT JOIN inventory_items i ON i.id = l.inventory_item_id
                 WHERE l.store_id = ? AND l.wc_product_id = ? AND l.wc_variation_id = ? AND l.status = 'mapped'",
                [$storeId, (int) $l['product_id'], (int) ($l['variation_id'] ?? 0)],
            );
            $unmapped = $unmapped || $listing === null;
            $qty = (int) $l['quantity'];
            $lineTotal = Currency::toMinor((string) $l['total'], $currency);
            $this->db->run(
                'INSERT INTO order_lines (order_id, wc_line_id, wc_product_id, wc_variation_id, listing_id, inventory_item_id, sku, name, option_label, qty, unit_price_minor, line_total_minor)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE listing_id = VALUES(listing_id), inventory_item_id = VALUES(inventory_item_id), qty = VALUES(qty),
                    unit_price_minor = VALUES(unit_price_minor), line_total_minor = VALUES(line_total_minor), sku = VALUES(sku), name = VALUES(name)',
                [$orderId, (int) $l['id'], (int) $l['product_id'], (int) ($l['variation_id'] ?? 0), $listing['id'] ?? null, $listing['inventory_item_id'] ?? null, $l['sku'] ?? null, (string) $l['name'],
                 $listing['option_label'] ?? null, $qty, $qty > 0 ? intdiv($lineTotal, $qty) : 0, $lineTotal],
            );
        }
        return $unmapped;
    }

    /** @return string|null an attention code when a person must act */
    private function driveLedger(int $orderId, int $storeId, array $o, string $status, ?string $previousPayment): ?string
    {
        $key = self::meta($o)['commerce_reservation_key'] ?? "store:{$storeId}:order:{$o['id']}";
        $reservation = $this->ledger->reservationByKey($key);
        if ($reservation !== null) {
            $this->db->run('UPDATE orders SET reservation_id = ? WHERE id = ?', [$reservation['id'], $orderId]);
        }
        $actor = 'store:' . $storeId;

        if (in_array($status, self::PAID, true)) {
            if ($reservation === null) {
                // The checkout hold never reached the hub (e.g. order created in WP admin). Hold now or flag it.
                $lines = $this->itemQuantities($orderId);
                if ($lines === null) {
                    return 'unmapped_item';
                }
                try {
                    $r = $this->ledger->reserve($storeId, (string) $o['id'], $lines, $key, $this->holdSeconds, $this->hardHoldSeconds);
                } catch (InsufficientStock) {
                    $this->event($orderId, 'stock.short', 'Paid, but the stock is no longer available', 'hub');
                    return 'paid_no_stock';
                }
                $this->db->run('UPDATE orders SET reservation_id = ? WHERE id = ?', [$r->reservationId, $orderId]);
                $reservation = ['id' => $r->reservationId];
                $this->event($orderId, 'stock.reserved', 'Stock reserved', 'hub');
            }
            $res = $this->ledger->confirm((int) $reservation['id'], $o['transaction_id'] ?? null, $actor);
            if (!$res->ok) {
                $this->event($orderId, 'stock.short', $res->exception === 'paid_after_release' ? 'Paid after the order was cancelled' : 'Paid after the stock hold lapsed, and the stock is gone', 'hub');
                return $res->exception === 'paid_after_release' ? 'paid_after_cancel' : 'paid_no_stock';
            }
            return null;
        }
        if ($reservation === null) {
            return null;
        }
        if ($status === 'on-hold') {
            $this->ledger->extend((int) $reservation['id'], $this->onHoldExtendSeconds, $actor);
        } elseif (in_array($status, ['failed', 'cancelled', 'refunded'], true)) {
            $r = $this->ledger->release((int) $reservation['id'], 'Storefront status: ' . $status, $actor);
            if (!$r->replayed) {
                $this->event($orderId, 'stock.released', 'Reserved stock returned to sale', 'hub');
            }
        }
        return null;
    }

    /** @return array<int, int>|null null when any line is unmapped */
    private function itemQuantities(int $orderId): ?array
    {
        $out = [];
        foreach ($this->db->all('SELECT inventory_item_id, qty FROM order_lines WHERE order_id = ?', [$orderId]) as $l) {
            if ($l['inventory_item_id'] === null) {
                return null;
            }
            $out[(int) $l['inventory_item_id']] = ($out[(int) $l['inventory_item_id']] ?? 0) + (int) $l['qty'];
        }
        return $out ?: null;
    }

    private function event(int $orderId, string $type, string $message, string $actor): void
    {
        $this->db->run('INSERT INTO order_events (order_id, type, message, actor, created_at) VALUES (?, ?, ?, ?, ?)', [$orderId, $type, $message, $actor, $this->clock->now()]);
    }

    /** Queues a customer notification once per order and event; the notification engine renders it from the stored order. */
    private function notify(int $orderId, string $event): void
    {
        $now = $this->clock->now();
        $this->db->run(
            "INSERT IGNORE INTO outbox (topic, dedupe_key, payload, status, next_attempt_at, created_at, updated_at) VALUES ('notify.customer', ?, ?, 'pending', ?, ?, ?)",
            ["notify:{$orderId}:{$event}", json_encode(['order_id' => $orderId, 'event' => $event]), $now, $now, $now],
        );
    }

    /**
     * Order meta written by the connector, from either a plain map or WooCommerce's REST meta_data list.
     * Keys deliberately have no leading underscore: WooCommerce hides "protected" _keys from REST responses.
     */
    private static function meta(array $o): array
    {
        $meta = $o['meta'] ?? [];
        foreach ($o['meta_data'] ?? [] as $m) {
            if (isset($m['key']) && str_starts_with((string) $m['key'], 'commerce_')) {
                $meta[$m['key']] = $m['value'];
            }
        }
        return $meta;
    }

    private static function address(array $a): array
    {
        $keys = ['first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone'];
        return array_intersect_key($a, array_flip($keys));
    }

    /** The inbox keeps the payload for retries, without anything a retry does not need. */
    private static function redactForInbox(array $o): array
    {
        unset($o['customer_ip_address'], $o['customer_user_agent'], $o['payment_url'], $o['_links']);
        return $o;
    }

    private static function utc(string $v): \DateTimeImmutable
    {
        return new \DateTimeImmutable($v, new \DateTimeZone('UTC'));
    }
}
