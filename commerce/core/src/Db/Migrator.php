<?php

declare(strict_types=1);

namespace Commerce\Core\Db;

/** Applies migrations/NNNN_name.sql in order, once each. DDL is not transactional in MySQL, so each file is recorded only after it fully succeeds. */
final class Migrator
{
    public function __construct(private readonly Database $db, private readonly string $dir)
    {
    }

    /** @return list<string> versions applied by this call */
    public function migrate(): array
    {
        $this->db->pdo()->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(64) PRIMARY KEY, applied_at DATETIME(6) NOT NULL) ENGINE=InnoDB');
        $done = array_column($this->db->all('SELECT version FROM schema_migrations'), 'version');
        $files = glob(rtrim($this->dir, '/') . '/*.sql') ?: [];
        sort($files);
        $applied = [];
        foreach ($files as $file) {
            $version = basename($file, '.sql');
            if (in_array($version, $done, true)) {
                continue;
            }
            foreach (self::statements((string) file_get_contents($file)) as $sql) {
                $this->db->pdo()->exec($sql);
            }
            $this->db->run('INSERT INTO schema_migrations (version, applied_at) VALUES (?, UTC_TIMESTAMP(6))', [$version]);
            $applied[] = $version;
        }
        return $applied;
    }

    /** @return list<string> */
    public static function statements(string $sql): array
    {
        $lines = array_filter(explode("\n", $sql), static fn (string $l) => !str_starts_with(ltrim($l), '--'));
        $parts = preg_split('/;\s*$/m', implode("\n", $lines)) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn (string $s) => $s !== ''));
    }
}
