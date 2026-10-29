<?php

declare(strict_types=1);

namespace Commerce\Core\Support;

use DateTimeImmutable;
use DateTimeZone;

/** Test clock: time only moves when advance() is called. */
final class FixedClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(string $at = '2026-11-01 10:00:00')
    {
        $this->now = new DateTimeImmutable($at, new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->modify(sprintf('%+d seconds', $seconds));
    }
}
