<?php

declare(strict_types=1);

namespace Commerce\Core\Access;

final class Forbidden extends \RuntimeException
{
    public function __construct(public readonly string $permission)
    {
        parent::__construct('You do not have permission to do this. Ask an owner to give your role access to "' . (Roles::PERMISSIONS[$permission] ?? $permission) . '".');
    }
}
