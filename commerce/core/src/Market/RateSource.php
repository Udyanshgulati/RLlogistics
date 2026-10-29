<?php

declare(strict_types=1);

namespace Commerce\Core\Market;

interface RateSource
{
    public function name(): string;

    /** @return array{base: string, as_of: string, rates: array<string, string>} rates per 1 unit of base, as decimal strings */
    public function fetch(): array;
}
