<?php

declare(strict_types=1);

namespace Commerce\Core\Integration;

final class CallbackHandler implements Handler
{
    /** @param \Closure(array): void $fn */
    public function __construct(private readonly \Closure $fn)
    {
    }

    public function handle(array $payload): void
    {
        ($this->fn)($payload);
    }
}
