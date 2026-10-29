<?php

declare(strict_types=1);

namespace Commerce\Core\Market;

/**
 * ISO 4217 minor-unit exponents for currencies the business may display or charge.
 * Being listed here only means the system can format and store the currency correctly;
 * whether it can be charged is decided per gateway in the market configuration.
 */
final class Currency
{
    private const MINOR_UNITS = [
        'INR' => 2, 'USD' => 2, 'EUR' => 2, 'GBP' => 2, 'AED' => 2, 'SAR' => 2, 'QAR' => 2, 'SGD' => 2, 'AUD' => 2,
        'CAD' => 2, 'NZD' => 2, 'CHF' => 2, 'SEK' => 2, 'NOK' => 2, 'DKK' => 2, 'HKD' => 2, 'MYR' => 2, 'ZAR' => 2,
        'JPY' => 0, 'KRW' => 0, 'BHD' => 3, 'KWD' => 3, 'OMR' => 3,
    ];

    public static function exponent(string $code): int
    {
        $code = strtoupper($code);
        if (!isset(self::MINOR_UNITS[$code])) {
            throw new \InvalidArgumentException("Currency {$code} is not configured.");
        }
        return self::MINOR_UNITS[$code];
    }

    public static function isKnown(string $code): bool
    {
        return isset(self::MINOR_UNITS[strtoupper($code)]);
    }

    /** "1499.5" INR => 149950. Rejects more decimals than the currency has, instead of silently rounding. */
    public static function toMinor(string $amount, string $code): int
    {
        $exp = self::exponent($code);
        if (!preg_match('/^(-)?(\d+)(?:\.(\d+))?$/', trim($amount), $m)) {
            throw new \InvalidArgumentException("\"{$amount}\" is not an amount.");
        }
        $frac = $m[3] ?? '';
        if (strlen(rtrim($frac, '0')) > $exp) {
            throw new \InvalidArgumentException("{$amount} has more decimals than {$code} allows.");
        }
        $minor = (int) ($m[2] . str_pad(substr($frac, 0, $exp), $exp, '0'));
        return ($m[1] ?? '') === '-' ? -$minor : $minor;
    }

    public static function fromMinor(int $minor, string $code): string
    {
        $exp = self::exponent($code);
        $sign = $minor < 0 ? '-' : '';
        $abs = (string) abs($minor);
        if ($exp === 0) {
            return $sign . $abs;
        }
        $abs = str_pad($abs, $exp + 1, '0', STR_PAD_LEFT);
        return $sign . substr($abs, 0, -$exp) . '.' . substr($abs, -$exp);
    }

    /** Display formatting with intl when available, e.g. "₹1,499.00", "$12.00", "¥1,500". */
    public static function format(int $minor, string $code, string $locale = 'en'): string
    {
        $code = strtoupper($code);
        if (class_exists(\NumberFormatter::class)) {
            $f = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
            $f->setAttribute(\NumberFormatter::FRACTION_DIGITS, self::exponent($code));
            $out = $f->formatCurrency((float) self::fromMinor($minor, $code), $code);
            if ($out !== false) {
                return $out;
            }
        }
        return $code . ' ' . self::fromMinor($minor, $code);
    }
}
