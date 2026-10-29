<?php

declare(strict_types=1);

namespace Commerce\Core\Market;

use Commerce\Core\Db\Database;
use Commerce\Core\Support\Clock;

/**
 * Display-only currency conversion. Rates are fetched by a scheduled job (refresh()), never during a
 * page view, and stored in fx_rates. If the newest stored rate is older than the configured maximum
 * age, convert() returns null and prices are shown in the base currency: a stale rate is never shown
 * as if current, and no rate is ever hard-coded.
 */
final class ExchangeRates
{
    public function __construct(private readonly Database $db, private readonly Clock $clock, private readonly int $maxAgeHours = 48)
    {
    }

    /** @return int currencies stored */
    public function refresh(RateSource $source): int
    {
        $feed = $source->fetch();
        $now = $this->clock->now();
        $n = 0;
        $this->db->transaction(function () use ($feed, $source, $now, &$n) {
            foreach ($feed['rates'] as $cur => $rate) {
                if (!Currency::isKnown($cur)) {
                    continue;
                }
                $this->db->run(
                    'INSERT INTO fx_rates (currency, base, rate, source, as_of, fetched_at) VALUES (?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE base = VALUES(base), rate = VALUES(rate), source = VALUES(source), as_of = VALUES(as_of), fetched_at = VALUES(fetched_at)',
                    [$cur, $feed['base'], $rate, $source->name(), $feed['as_of'], $now],
                );
                $n++;
            }
        });
        return $n;
    }

    /** @return array{minor: int, rate: string, as_of: string, source: string}|null null when no fresh rate exists */
    public function convert(int $minor, string $from, string $to): ?array
    {
        if ($from === $to) {
            return ['minor' => $minor, 'rate' => '1', 'as_of' => $this->clock->now()->format('Y-m-d'), 'source' => 'identity'];
        }
        $rows = $this->db->all('SELECT * FROM fx_rates WHERE currency IN (?, ?)', [$from, $to]);
        $by = array_column($rows, null, 'currency');
        if (!isset($by[$from], $by[$to]) || $by[$from]['base'] !== $by[$to]['base']) {
            return null;
        }
        $cutoff = $this->clock->now()->modify("-{$this->maxAgeHours} hours");
        foreach ([$by[$from], $by[$to]] as $r) {
            if (new \DateTimeImmutable($r['fetched_at'], new \DateTimeZone('UTC')) < $cutoff) {
                return null;
            }
        }
        // amount_to = amount_from / rate_from * rate_to, adjusted for each currency's minor units.
        // Float precision is ample for a display estimate; charged amounts never come from here.
        $rate = (float) $by[$to]['rate'] / (float) $by[$from]['rate'];
        $value = $minor * $rate * 10 ** (Currency::exponent($to) - Currency::exponent($from));
        $rate = rtrim(rtrim(sprintf('%.10F', $rate), '0'), '.');
        return ['minor' => (int) round($value), 'rate' => $rate, 'as_of' => $by[$to]['as_of'], 'source' => $by[$to]['source']];
    }
}
