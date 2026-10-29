<?php

declare(strict_types=1);

namespace Commerce\Core\Market;

/**
 * The one market configuration all three storefronts read (served by the hub, cached by each store).
 * Validated on load so a typo fails loudly at deploy time instead of mispricing at checkout.
 */
final class MarketConfig
{
    /** @var array<string, string> country => market code */
    private array $countryIndex = [];
    private ?string $wildcard = null;

    private function __construct(public readonly array $data)
    {
    }

    public static function fromFile(string $path): self
    {
        return self::fromArray(json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR));
    }

    public static function fromArray(array $d): self
    {
        $c = new self($d);
        $problems = [];
        if (!Currency::isKnown($d['base_currency'] ?? '')) {
            $problems[] = 'base_currency is not a configured currency';
        }
        foreach ($d['markets'] ?? [] as $code => $m) {
            if (!Currency::isKnown($m['display_currency'] ?? '')) {
                $problems[] = "{$code}: unknown display_currency";
            }
            foreach ($m['checkout_currencies'] ?? [] as $cur) {
                if (!Currency::isKnown($cur)) {
                    $problems[] = "{$code}: unknown checkout currency {$cur}";
                }
            }
            if (($m['countries'] ?? null) === '*') {
                if ($c->wildcard !== null) {
                    $problems[] = "{$code}: only one market may cover every other country";
                }
                $c->wildcard = $code;
                continue;
            }
            foreach ((array) ($m['countries'] ?? []) as $country) {
                if (!CountryCode::isValid($country)) {
                    $problems[] = "{$code}: invalid country {$country}";
                } elseif (isset($c->countryIndex[$country])) {
                    $problems[] = "{$country} is in both {$c->countryIndex[$country]} and {$code}";
                } else {
                    $c->countryIndex[$country] = $code;
                }
            }
        }
        if (!isset($d['markets'][$d['default_market'] ?? ''])) {
            $problems[] = 'default_market does not exist';
        }
        foreach ($d['gateways'] ?? [] as $id => $g) {
            foreach ($g['verified_currencies'] ?? [] as $cur) {
                if (!Currency::isKnown($cur)) {
                    $problems[] = "gateway {$id}: unknown currency {$cur}";
                }
            }
        }
        if ($problems !== []) {
            throw new \InvalidArgumentException('Invalid market configuration: ' . implode('; ', $problems));
        }
        return $c;
    }

    public function marketFor(string $country): string
    {
        return $this->countryIndex[strtoupper($country)] ?? $this->wildcard ?? $this->data['default_market'];
    }

    public function market(string $code): array
    {
        return ['code' => $code] + ($this->data['markets'][$code] ?? throw new \InvalidArgumentException("Unknown market {$code}."));
    }

    public function defaultMarket(): string
    {
        return $this->data['default_market'];
    }

    public function baseCurrency(): string
    {
        return $this->data['base_currency'];
    }

    public function gateways(): array
    {
        return $this->data['gateways'] ?? [];
    }

    public function isMarketEnabledForBrand(string $market, string $brandCode): bool
    {
        return !in_array($market, $this->data['brands'][$brandCode]['disabled_markets'] ?? [], true);
    }

    public function shipping(): array
    {
        return ($this->data['shipping'] ?? []) + ['destination_allowlist' => [], 'allow_non_express_fallback' => false];
    }

    public function rates(): array
    {
        return ($this->data['rates'] ?? []) + ['max_age_hours' => 48, 'on_stale' => 'show_base_currency'];
    }
}
