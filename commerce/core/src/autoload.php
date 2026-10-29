<?php

declare(strict_types=1);

// PSR-4 autoloader so the core runs without Composer installed (composer.json maps the same paths).
spl_autoload_register(static function (string $class): void {
    $map = [
        'Commerce\\Core\\Tests\\' => __DIR__ . '/../tests/',
        'Commerce\\Core\\' => __DIR__ . '/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});
