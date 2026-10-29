<?php

declare(strict_types=1);

namespace Commerce\Core\Integration;

interface Handler
{
    /** Throw to retry later; throw PermanentFailure when retrying cannot help. */
    public function handle(array $payload): void;
}
