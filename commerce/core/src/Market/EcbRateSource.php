<?php

declare(strict_types=1);

namespace Commerce\Core\Market;

/**
 * European Central Bank euro reference rates (free, published on working days, includes INR, USD, GBP).
 * The ECB states they are for information only, which matches our use: display conversion, never charging.
 * Status: parser tested with a fixture; the live download is not exercised in automated tests.
 */
final class EcbRateSource implements RateSource
{
    public const URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

    /** @param (callable(): string)|null $download returns the XML body */
    public function __construct(private $download = null)
    {
    }

    public function name(): string
    {
        return 'ecb';
    }

    public function fetch(): array
    {
        $xml = $this->download !== null ? ($this->download)() : $this->get();
        return self::parse($xml);
    }

    public static function parse(string $xml): array
    {
        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, options: LIBXML_NONET);
        libxml_use_internal_errors($prev);
        if ($doc === false) {
            throw new \RuntimeException('The ECB rate feed could not be read.');
        }
        $doc->registerXPathNamespace('e', 'http://www.ecb.int/vocabulary/2002-08-01/eurofxref');
        $day = $doc->xpath('//e:Cube[@time]')[0] ?? null;
        if ($day === null) {
            throw new \RuntimeException('The ECB rate feed has no rates.');
        }
        $rates = [];
        foreach ($day->children() as $cube) {
            $cur = (string) $cube['currency'];
            $rate = (string) $cube['rate'];
            if (preg_match('/^[A-Z]{3}$/', $cur) && preg_match('/^\d+(\.\d+)?$/', $rate) && (float) $rate > 0) {
                $rates[$cur] = $rate;
            }
        }
        $rates['EUR'] = '1';
        return ['base' => 'EUR', 'as_of' => (string) $day['time'], 'rates' => $rates];
    }

    private function get(): string
    {
        $ch = curl_init(self::URL);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION => false]);
        $body = curl_exec($ch);
        if ($body === false || curl_getinfo($ch, CURLINFO_RESPONSE_CODE) !== 200) {
            throw new \RuntimeException('The ECB rate feed is unavailable.');
        }
        return (string) $body;
    }
}
