<?php

declare(strict_types=1);

namespace Commerce\Hub;

use Commerce\Core\Db\Database;
use Commerce\Core\Support\Clock;

/** Fixed-window counter in the database (no Redis). Good enough for login and API abuse limits. */
final class RateLimiter
{
    public function __construct(private readonly Database $db, private readonly Clock $clock)
    {
    }

    /** @return bool true when the call is allowed (and counted) */
    public function hit(string $bucket, int $max, int $windowSeconds): bool
    {
        $now = $this->clock->now();
        $key = hash('sha256', $bucket);
        return $this->db->transaction(function () use ($key, $max, $windowSeconds, $now) {
            $row = $this->db->one('SELECT * FROM rate_limits WHERE bucket = ? FOR UPDATE', [$key]);
            if ($row === null || new \DateTimeImmutable($row['window_start'], new \DateTimeZone('UTC')) < $now->modify("-{$windowSeconds} seconds")) {
                $this->db->run('REPLACE INTO rate_limits (bucket, window_start, hits) VALUES (?, ?, 1)', [$key, $now]);
                return true;
            }
            if ((int) $row['hits'] >= $max) {
                return false;
            }
            $this->db->run('UPDATE rate_limits SET hits = hits + 1 WHERE bucket = ?', [$key]);
            return true;
        });
    }
}
