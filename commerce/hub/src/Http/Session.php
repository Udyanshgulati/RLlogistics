<?php

declare(strict_types=1);

namespace Commerce\Hub\Http;

interface Session
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function remove(string $key): void;

    /** New session id after login, so a fixed pre-login id is useless. */
    public function regenerate(): void;

    public function destroy(): void;
}
