<?php

declare(strict_types=1);

namespace Commerce\Core\Inventory;

use Commerce\Core\Db\Database;
use Commerce\Core\Integration\StoreClient;
use Commerce\Core\Support\Clock;

/**
 * Runs every minute. For each unpaid hold that is due, it asks the storefront what happened to the
 * order before letting the stock go, because a missing webhook is not proof that payment failed:
 *   paid (processing/completed) → confirm      on-hold → extend
 *   pending/failed/cancelled/missing → expire   storefront unreachable → keep holding and retry
 * A hold whose storefront stays unreachable until the hard limit expires anyway and is flagged.
 */
final class ExpiryWorker
{
    public function __construct(
        private readonly Database $db,
        private readonly Ledger $ledger,
        private readonly StoreClient $client,
        private readonly Clock $clock,
        private readonly int $extendSeconds = 900,
        private readonly int $retrySeconds = 300,
    ) {
    }

    /** @return array<string, int> counts by outcome */
    public function run(int $limit = 100): array
    {
        $out = ['confirmed' => 0, 'extended' => 0, 'expired' => 0, 'kept' => 0, 'forced' => 0];
        foreach ($this->ledger->dueForExpiry($limit) as $id) {
            $r = $this->ledger->reservation($id);
            $hard = new \DateTimeImmutable($r['hard_expires_at'], new \DateTimeZone('UTC'));
            try {
                $status = $this->client->orderStatus((int) $r['store_id'], (int) $r['order_ref']);
            } catch (\Throwable) {
                if ($this->clock->now() >= $hard) {
                    $this->ledger->expire($id, 'system:expiry');
                    $this->db->run("UPDATE reservations SET exception = 'expired_store_unreachable' WHERE id = ?", [$id]);
                    $out['forced']++;
                } else {
                    $this->ledger->extend($id, $this->retrySeconds, 'system:expiry');
                    $out['kept']++;
                }
                continue;
            }
            if (in_array($status, ['processing', 'completed'], true)) {
                $this->ledger->confirm($id, null, 'system:expiry');
                $out['confirmed']++;
            } elseif ($status === 'on-hold' && $this->clock->now() < $hard) {
                $this->ledger->extend($id, $this->extendSeconds, 'system:expiry');
                $out['extended']++;
            } else {
                $this->ledger->expire($id, 'system:expiry');
                $out['expired']++;
            }
        }
        return $out;
    }
}
