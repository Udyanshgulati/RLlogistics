<?php

declare(strict_types=1);

namespace Commerce\Hub\Http;

/** PHP sessions with strict, HttpOnly, SameSite=Lax cookies; Secure and the __Host- prefix over HTTPS. */
final class NativeSession implements Session
{
    public function __construct(bool $secure, int $idleSeconds = 43200)
    {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) $idleSeconds);
        session_name($secure ? '__Host-hub' : 'hub_session');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
        $now = time();
        if (isset($_SESSION['_seen']) && $now - $_SESSION['_seen'] > $idleSeconds) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['_seen'] = $now;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }
}
