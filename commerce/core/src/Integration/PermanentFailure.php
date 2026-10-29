<?php

declare(strict_types=1);

namespace Commerce\Core\Integration;

/** A failure that retrying will not fix (bad data, missing configuration). Goes straight to Integration health. */
final class PermanentFailure extends \RuntimeException
{
}
