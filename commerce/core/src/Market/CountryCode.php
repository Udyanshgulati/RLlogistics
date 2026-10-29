<?php

declare(strict_types=1);

namespace Commerce\Core\Market;

final class CountryCode
{
    /** Codes some geolocation sources return that are not real destinations (unknown, Tor, anonymous proxy, satellite, region-only). */
    private const NOT_A_COUNTRY = ['XX', 'T1', 'A1', 'A2', 'O1', 'EU', 'AP', 'ZZ'];

    public static function isValid(?string $code): bool
    {
        return $code !== null && preg_match('/^[A-Z]{2}$/', $code) === 1 && !in_array($code, self::NOT_A_COUNTRY, true);
    }

    public static function clean(?string $code): ?string
    {
        $code = $code === null ? null : strtoupper(trim($code));
        return self::isValid($code) ? $code : null;
    }
}
