<?php

declare(strict_types=1);

namespace Commerce\Core\Support;

use DateTimeImmutable;

interface Clock
{
    /** Always UTC. */
    public function now(): DateTimeImmutable;
}
