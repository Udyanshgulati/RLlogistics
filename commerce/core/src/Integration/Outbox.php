<?php

declare(strict_types=1);

namespace Commerce\Core\Integration;

use Commerce\Core\Db\Database;
use Commerce\Core\Support\Clock;

/**
 * Background work queue backed by the database (no Redis or broker to run).
 * Rows are written in the same transaction as the change that caused them, so work is never lost
 * when a server restarts. Workers claim rows with SKIP LOCKED, so several can run at once.
 *
 * Retry policy: attempts back off 1, 2, 4 … minutes (capped at 1 hour); after MAX_ATTEMPTS the row is
 * "dead" and appears on Integration health until someone retries it.
 */
final class Outbox
{
    public const MAX_ATTEMPTS = 8;
    private const LEASE_SECONDS = 120;

    /** @param array<string, Handler> $handlers topic => handler */
    public function __construct(private readonly Database $db, private readonly Clock $clock, private readonly array $handlers)
    {
    }

    /** Processes up to $limit due rows. @return array{done: int, failed: int} */
    public function runOnce(int $limit = 50): array
    {
        $done = $failed = 0;
        foreach ($this->claim($limit) as $row) {
            $handler = $this->handlers[$row['topic']] ?? null;
            try {
                if ($handler === null) {
                    throw new PermanentFailure("No handler is configured for {$row['topic']}.");
                }
                $handler->handle(json_decode($row['payload'], true, flags: JSON_THROW_ON_ERROR));
                $this->db->run("UPDATE outbox SET status = 'done', locked_until = NULL, last_error = NULL, updated_at = ? WHERE id = ?", [$this->clock->now(), $row['id']]);
                $done++;
            } catch (\Throwable $e) {
                $this->failed($row, $e);
                $failed++;
            }
        }
        return ['done' => $done, 'failed' => $failed];
    }

    /** Manual retry from Integration health: due now, attempts reset. */
    public function retry(int $id): void
    {
        $this->db->run("UPDATE outbox SET status = 'pending', attempts = 0, next_attempt_at = ?, locked_until = NULL, updated_at = ? WHERE id = ? AND status = 'dead'", [$this->clock->now(), $this->clock->now(), $id]);
    }

    private function claim(int $limit): array
    {
        return $this->db->transaction(function () use ($limit) {
            $now = $this->clock->now();
            // Pending rows that are due, plus rows whose worker died mid-lease.
            $rows = $this->db->all(
                "SELECT * FROM outbox WHERE (status = 'pending' AND next_attempt_at <= ?) OR (status = 'processing' AND locked_until < ?)
                 ORDER BY next_attempt_at LIMIT " . max(1, $limit) . ' FOR UPDATE SKIP LOCKED',
                [$now, $now],
            );
            foreach ($rows as $r) {
                // Coalescing keys (stock pushes) are released on claim, so a change made while this push runs queues a fresh one.
                // Notification keys stay forever so the same message is never queued twice.
                $releaseKey = str_starts_with((string) $r['dedupe_key'], 'stock.push:');
                $this->db->run(
                    "UPDATE outbox SET status = 'processing', attempts = attempts + 1, locked_until = ?, dedupe_key = IF(?, NULL, dedupe_key), updated_at = ? WHERE id = ?",
                    [$now->modify('+' . self::LEASE_SECONDS . ' seconds'), $releaseKey, $now, $r['id']],
                );
            }
            return $rows;
        });
    }

    private function failed(array $row, \Throwable $e): void
    {
        $attempts = (int) $row['attempts'] + 1;
        $dead = $e instanceof PermanentFailure || $attempts >= self::MAX_ATTEMPTS;
        $delay = min(3600, 60 * (2 ** ($attempts - 1)));
        $this->db->run(
            'UPDATE outbox SET status = ?, locked_until = NULL, next_attempt_at = ?, last_error = ?, updated_at = ? WHERE id = ?',
            [$dead ? 'dead' : 'pending', $this->clock->now()->modify("+{$delay} seconds"), mb_substr(self::safeMessage($e), 0, 1000), $this->clock->now(), $row['id']],
        );
    }

    /** Error text for the dashboard: never includes credentials that a client library may echo. */
    public static function safeMessage(\Throwable $e): string
    {
        return preg_replace(['/(consumer_(key|secret)|password|token|authorization)[=:]\s*\S+/i', '/ck_[a-f0-9]{8,}|cs_[a-f0-9]{8,}/i'], ['$1=[redacted]', '[redacted]'], $e->getMessage()) ?? 'Error';
    }
}
