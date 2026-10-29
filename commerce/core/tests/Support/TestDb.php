<?php

declare(strict_types=1);

namespace Commerce\Core\Tests\Support;

use Commerce\Core\Db\Database;
use Commerce\Core\Db\Migrator;

/**
 * A disposable database for tests. Refuses to run unless the database name starts with "commerce_test",
 * so a misconfigured DSN can never point the suite at real data.
 *
 * Env: TEST_DB_HOST (default localhost), TEST_DB_PORT, TEST_DB_SOCKET, TEST_DB_USER (root), TEST_DB_PASS (''), TEST_DB_NAME (commerce_test).
 */
final class TestDb
{
    public static function name(): string
    {
        $name = getenv('TEST_DB_NAME') ?: 'commerce_test';
        if (!str_starts_with($name, 'commerce_test')) {
            throw new \RuntimeException('TEST_DB_NAME must start with "commerce_test".');
        }
        return $name;
    }

    public static function dsn(bool $withDb = true): string
    {
        $host = getenv('TEST_DB_HOST') ?: 'localhost';
        $dsn = "mysql:host={$host};charset=utf8mb4";
        if ($socket = getenv('TEST_DB_SOCKET')) {
            $dsn .= ";unix_socket={$socket}";
        }
        if ($port = getenv('TEST_DB_PORT')) {
            $dsn .= ";port={$port}";
        }
        return $withDb ? $dsn . ';dbname=' . self::name() : $dsn;
    }

    public static function connect(): Database
    {
        return Database::connect(self::dsn(), getenv('TEST_DB_USER') ?: 'root', getenv('TEST_DB_PASS') ?: '');
    }

    /** Drops and recreates the test database, applies all migrations, seeds brands, stores and the Delhi warehouse. */
    public static function fresh(): Database
    {
        $server = Database::connect(self::dsn(false), getenv('TEST_DB_USER') ?: 'root', getenv('TEST_DB_PASS') ?: '');
        $name = self::name();
        $server->pdo()->exec("DROP DATABASE IF EXISTS `{$name}`");
        $server->pdo()->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $db = self::connect();
        (new Migrator($db, __DIR__ . '/../../migrations'))->migrate();
        self::seed($db);
        return $db;
    }

    /** Brand codes and SKU prefixes here are test fixtures, not approved client values. */
    public static function seed(Database $db): void
    {
        $db->run("INSERT INTO brands (id, code, name, sku_prefix) VALUES (1, 'milan-icons', 'Milan Icons', 'MI'), (2, 'significant-sports', 'Significant Sports', 'SS'), (3, 'schermo', 'Schermo', 'SCH')");
        $db->run("INSERT INTO stores (id, brand_id, code, name, base_url) VALUES (1, 1, 'milan-icons', 'Milan Icons', NULL), (2, 2, 'significant-sports', 'Significant Sports', NULL), (3, 3, 'schermo', 'Schermo', 'https://schermo.in')");
        $db->run("INSERT INTO warehouses (id, code, name, country_code, city) VALUES (1, 'DEL1', 'Delhi warehouse', 'IN', 'Delhi')");
    }
}
