<?php

declare(strict_types=1);

namespace Commerce\Core\Support;

use Commerce\Core\Db\Database;

/** Append-only record of who changed what. Details must never contain secrets, tokens or full addresses. */
final class Audit
{
    public function __construct(private readonly Database $db, private readonly Clock $clock)
    {
    }

    public function log(string $actor, string $action, string $entityType, string|int $entityId, array $details = [], ?string $ip = null): void
    {
        $this->db->run(
            'INSERT INTO audit_log (actor, action, entity_type, entity_id, details, ip, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$actor, $action, $entityType, (string) $entityId, $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $ip, $this->clock->now()],
        );
    }
}
