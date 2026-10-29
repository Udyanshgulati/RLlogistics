<?php

declare(strict_types=1);

// Shared start-up for the web front controller and the CLI.
require __DIR__ . '/../core/src/autoload.php';
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Commerce\\Hub\\')) {
        $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen('Commerce\\Hub\\'))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

/**
 * Environment: real environment variables win; a .env file next to this one (never committed) fills gaps.
 * Values are also exported with putenv() for library code that reads getenv().
 */
function hub_env(): array
{
    $env = [];
    $file = getenv('HUB_ENV_FILE') ?: __DIR__ . '/.env';
    if (is_file($file)) {
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/', $line, $m) && !str_starts_with(trim($line), '#')) {
                $env[$m[1]] = trim($m[2], "\"'");
            }
        }
    }
    foreach (getenv() as $k => $v) {
        $env[$k] = $v;
    }
    foreach ($env as $k => $v) {
        if (getenv($k) === false) {
            putenv("{$k}={$v}");
        }
    }
    return $env;
}

function hub_db(array $env): Commerce\Core\Db\Database
{
    return Commerce\Core\Db\Database::connect($env['DB_DSN'] ?? 'mysql:host=localhost;dbname=commerce_hub;charset=utf8mb4', $env['DB_USER'] ?? 'hub', $env['DB_PASSWORD'] ?? '');
}
