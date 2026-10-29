<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Commerce\\Hub\\Tests\\')) {
        require __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen('Commerce\\Hub\\Tests\\'))) . '.php';
    }
});
