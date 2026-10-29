<?php

declare(strict_types=1);

namespace Commerce\Core\Integration;

use Commerce\Core\Db\Database;
use Commerce\Core\Market\Currency;
use Commerce\Core\Orders\Returns;

/**
 * Handlers that carry dashboard requests to the storefront. The storefront answers with an order
 * snapshot (cancel, mark paid), so the hub's order record changes only once WooCommerce has done it.
 */
final class StoreRequestHandlers
{
    public function __construct(private readonly Database $db, private readonly StoreClient $client, private readonly Returns $returns)
    {
    }

    /** @return array<string, Handler> */
    public function handlers(): array
    {
        return [
            'store.order_action' => new CallbackHandler(fn (array $p) => $this->orderAction($p)),
            'store.shipment' => new CallbackHandler(fn (array $p) => $this->shipment($p)),
            'store.refund' => new CallbackHandler(fn (array $p) => $this->refund($p)),
        ];
    }

    private function orderAction(array $p): void
    {
        $o = $this->order($p['order_id']);
        $status = match ($p['action']) {
            'cancel' => 'cancelled',
            'mark_paid' => 'processing',
            default => throw new PermanentFailure('Unknown order action.'),
        };
        $this->client->setOrderStatus((int) $o['store_id'], (int) $o['wc_order_id'], $status, "Requested from the operations dashboard by {$p['actor']}: {$p['reason']}");
    }

    private function shipment(array $p): void
    {
        $o = $this->order($p['order_id']);
        $s = $this->db->one('SELECT * FROM shipments WHERE id = ?', [$p['shipment_id']]) ?? throw new PermanentFailure('Unknown shipment.');
        $this->client->addShipmentNote((int) $o['store_id'], (int) $o['wc_order_id'], "Your order has been dispatched with {$s['carrier']} ({$s['service']}). Tracking number: {$s['tracking_number']}.");
        $left = (int) $this->db->value('SELECT SUM(qty - qty_shipped) FROM order_lines WHERE order_id = ?', [$o['id']]);
        if ($left === 0) {
            $this->client->setOrderStatus((int) $o['store_id'], (int) $o['wc_order_id'], 'completed', 'All items dispatched.');
        }
    }

    private function refund(array $p): void
    {
        $o = $this->order($p['order_id']);
        $rma = (string) $this->db->value('SELECT rma_number FROM returns WHERE id = ?', [$p['return_id']]);
        $marker = "[hub {$rma}]";
        $existing = $this->client->findRefund((int) $o['store_id'], (int) $o['wc_order_id'], $marker);
        if ($existing !== null) {
            $this->returns->recordRefundResult((int) $p['return_id'], true, $existing, null);
            return;
        }
        try {
            $ref = $this->client->refund((int) $o['store_id'], (int) $o['wc_order_id'], Currency::fromMinor((int) $p['amount_minor'], $p['currency']), "Return {$rma} {$marker}");
        } catch (PermanentFailure $e) {
            $this->returns->recordRefundResult((int) $p['return_id'], false, null, $e->getMessage());
            throw $e;
        }
        $this->returns->recordRefundResult((int) $p['return_id'], true, $ref, null);
    }

    private function order(int $id): array
    {
        return $this->db->one('SELECT * FROM orders WHERE id = ?', [$id]) ?? throw new PermanentFailure('Unknown order.');
    }
}
