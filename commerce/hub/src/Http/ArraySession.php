<?php

declare(strict_types=1);

namespace Commerce\Hub\Http;

/** In-memory session for tests and the CLI. */
final class ArraySession implements Session
{
    public array $data = [];
    public int $regenerated = 0;

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function regenerate(): void
    {
        $this->regenerated++;
    }

    public function destroy(): void
    {
        $this->data = [];
        $this->regenerated++;
    }
}
