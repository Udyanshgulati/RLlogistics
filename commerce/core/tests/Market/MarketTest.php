<?php

declare(strict_types=1);

namespace Commerce\Core\Tests\Market;

use Commerce\Core\Market\Availability;
use Commerce\Core\Market\CheckoutPolicy;
use Commerce\Core\Market\CountryResolver;
use Commerce\Core\Market\Currency;
use Commerce\Core\Market\EcbRateSource;
use Commerce\Core\Market\ExchangeRates;
use Commerce\Core\Market\MarketConfig;
use Commerce\Core\Market\PriceResolver;
use Commerce\Core\Market\RateSource;
use Commerce\Core\Market\Signals;
use Commerce\Core\Support\FixedClock;
use Commerce\Core\Tests\Support\TestCase;
use Commerce\Core\Tests\Support\TestDb;

final class MarketTest extends TestCase
{
    private MarketConfig $config;
    private CountryResolver $resolver;

    public function setUp(): void
    {
        $data = json_decode((string) file_get_contents(__DIR__ . '/../../../config/markets.example.json'), true);
        // Fixture: pretend two gateways were verified for these currencies. Real values come from the merchant accounts.
        $data['gateways']['razorpay'] = ['enabled' => true, 'verified_currencies' => ['INR', 'USD'], 'countries' => '*'];
        $data['gateways']['ppcp-gateway'] = ['enabled' => true, 'verified_currencies' => ['USD', 'GBP'], 'countries' => '*', 'exclude_countries' => ['IN']];
        $data['brands']['schermo']['disabled_markets'] = ['AE'];
        $this->config = MarketConfig::fromArray($data);
        $this->resolver = new CountryResolver($this->config);
    }

    public function testTheExampleConfigurationIsValid(): void
    {
        $this->assertSame('EUROZONE', MarketConfig::fromFile(__DIR__ . '/../../../config/markets.example.json')->marketFor('IT'));
    }

    public function testVisitorsFromIndiaUsUkAndEuGetTheirMarkets(): void
    {
        $cases = ['IN' => ['IN', 'INR'], 'US' => ['US', 'USD'], 'GB' => ['GB', 'GBP'], 'DE' => ['EUROZONE', 'EUR'], 'JP' => ['INTL', 'INR']];
        foreach ($cases as $country => [$market, $currency]) {
            $r = $this->resolver->browsing(new Signals(cdnCountry: $country));
            $this->assertSame($market, $r->market, $country);
            $this->assertSame('cdn', $r->source);
            $this->assertSame($currency, $this->config->market($r->market)['display_currency']);
        }
    }

    public function testUnknownLocationFallsBackToTheDefaultAndShowsTheSelector(): void
    {
        foreach ([null, 'XX', 'T1', 'zz', 'not-a-country'] as $header) {
            $r = $this->resolver->browsing(new Signals(cdnCountry: $header));
            $this->assertSame('INTL', $r->market);
            $this->assertTrue($r->showSelector);
        }
    }

    public function testManualChoiceWinsAndVpnMismatchOnlySuggests(): void
    {
        // Shopper in the UK on a US VPN exit chose United Kingdom earlier in this session.
        $r = $this->resolver->browsing(new Signals(sessionChoice: 'GB', cdnCountry: 'US'));
        $this->assertSame('GB', $r->market);
        $this->assertSame('session', $r->source);
        $this->assertSame('US', $r->suggestCountry, 'a banner, not a switch');
        $dismissed = $this->resolver->browsing(new Signals(sessionChoice: 'GB', cdnCountry: 'US', dismissed: ['US']));
        $this->assertNull($dismissed->suggestCountry, 'not shown again once dismissed');
    }

    public function testReturningVisitorKeepsSavedPreference(): void
    {
        $r = $this->resolver->browsing(new Signals(savedPreference: 'FR', cdnCountry: 'FR'));
        $this->assertSame('EUROZONE', $r->market);
        $this->assertSame('saved', $r->source);
        $this->assertNull($r->suggestCountry);
    }

    public function testShippingAddressDecidesTheOrderMarket(): void
    {
        $r = $this->resolver->checkout(new Signals(cdnCountry: 'US', shippingCountry: 'IN'));
        $this->assertSame('IN', $r->market);
        $this->assertSame('shipping', $r->source);
        $this->assertTrue($r->repriceRequired);
        $this->assertFalse($this->resolver->checkout(new Signals(sessionChoice: 'US', shippingCountry: 'US'))->repriceRequired);
    }

    public function testDevOverrideIsIgnoredInProduction(): void
    {
        $this->assertSame('INTL', $this->resolver->browsing(new Signals(devOverride: 'GB'))->market);
        $dev = new CountryResolver($this->config, allowDevOverride: true);
        $this->assertSame('GB', $dev->browsing(new Signals(devOverride: 'GB'))->market);
    }

    public function testUnsupportedCheckoutCurrencyFallsBackAndTellsTheShopper(): void
    {
        $policy = new CheckoutPolicy($this->config);
        $us = $policy->decide('US', 'USD', 'US', 'milan-icons');
        $this->assertSame('USD', $us->currency);
        $this->assertSame(['razorpay', 'ppcp-gateway'], $us->gateways);
        $this->assertNull($us->notice);

        $eu = $policy->decide('EUROZONE', 'EUR', 'DE', 'milan-icons'); // no gateway verified for EUR
        $this->assertSame('INR', $eu->currency);
        $this->assertSame(['razorpay'], $eu->gateways);
        $this->assertStringContains('charged in INR', $eu->notice);

        $in = $policy->decide('IN', 'INR', 'IN', 'milan-icons');
        $this->assertSame(['razorpay'], $in->gateways, 'PayPal India accounts cannot take domestic payments');
    }

    public function testNoVerifiedGatewayMeansNoCheckoutRatherThanAFakeOne(): void
    {
        $policy = new CheckoutPolicy(MarketConfig::fromFile(__DIR__ . '/../../../config/markets.example.json'));
        $d = $policy->decide('US', 'USD', 'US', 'schermo');
        $this->assertFalse($d->canCheckout());
        $this->assertStringContains('not available', $d->notice);
    }

    public function testBrandSpecificMarketRestrictions(): void
    {
        $a = new Availability($this->config);
        $this->assertTrue($a->check('milan-icons', 'AE')['available']);
        $this->assertFalse($a->check('schermo', 'AE')['available']);
        $this->assertFalse($a->check('milan-icons', 'US', ['blocked_countries' => ['US']])['available']);
        $this->assertFalse($a->check('milan-icons', 'GB', ['allowed_countries' => ['IN']])['available']);
        $this->assertFalse((new CheckoutPolicy($this->config))->decide('AE', 'AED', 'AE', 'schermo')->canCheckout());
    }

    public function testCurrencyMinorUnitsAreRespected(): void
    {
        $this->assertSame(149950, Currency::toMinor('1499.5', 'INR'));
        $this->assertSame(1500, Currency::toMinor('1500', 'JPY'));
        $this->assertSame(1234, Currency::toMinor('1.234', 'KWD'));
        $this->assertSame('1.234', Currency::fromMinor(1234, 'KWD'));
        $this->assertSame('0.05', Currency::fromMinor(5, 'USD'));
        $this->assertSame('-12.30', Currency::fromMinor(-1230, 'EUR'));
        $this->assertThrows(\InvalidArgumentException::class, fn () => Currency::toMinor('10.5', 'JPY'), 'more decimals');
        $this->assertThrows(\InvalidArgumentException::class, fn () => Currency::toMinor('1,000', 'INR'));
    }

    public function testRatesAreConvertedThenWithheldWhenTheFeedFails(): void
    {
        $db = TestDb::fresh();
        $clock = new FixedClock();
        $rates = new ExchangeRates($db, $clock, 48);
        $xml = '<?xml version="1.0"?><gesmes:Envelope xmlns:gesmes="http://www.gesmes.org/xml/2002-08-01" xmlns="http://www.ecb.int/vocabulary/2002-08-01/eurofxref"><Cube><Cube time="2026-10-30"><Cube currency="USD" rate="1.0800"/><Cube currency="INR" rate="90.00"/><Cube currency="JPY" rate="162.0"/><Cube currency="ZZZ" rate="1"/></Cube></Cube></gesmes:Envelope>';
        $this->assertSame(4, $rates->refresh(new EcbRateSource(fn () => $xml)), 'USD, INR, JPY, EUR; unknown currencies skipped');

        $c = $rates->convert(900000, 'INR', 'USD'); // ₹9,000.00
        $this->assertSame(10800, $c['minor']);      // $108.00 at 1.08/90
        $this->assertSame('2026-10-30', $c['as_of']);
        $this->assertSame(1800, $rates->convert(100000, 'INR', 'JPY')['minor'], 'JPY has no minor unit');

        $failing = new class implements RateSource {
            public function name(): string { return 'ecb'; }
            public function fetch(): array { throw new \RuntimeException('feed down'); }
        };
        $this->assertThrows(\RuntimeException::class, fn () => $rates->refresh($failing));
        $clock->advance(47 * 3600);
        $this->assertSame(10800, $rates->convert(900000, 'INR', 'USD')['minor'], 'last good rate within the allowed age');
        $clock->advance(2 * 3600);
        $this->assertNull($rates->convert(900000, 'INR', 'USD'), 'too old: no conversion, never a guess');

        $prices = new PriceResolver($db, $rates, 'INR');
        $p = $prices->price(1, 10, 0, 900000, 'USD', 'INR');
        $this->assertSame(['minor' => 900000, 'currency' => 'INR', 'kind' => 'base'], array_intersect_key($p, array_flip(['minor', 'currency', 'kind'])));
    }

    public function testMarketPriceWinsOverConversionAndConversionIsLabelledApproximate(): void
    {
        $db = TestDb::fresh();
        $clock = new FixedClock();
        $rates = new ExchangeRates($db, $clock);
        $rates->refresh(new EcbRateSource(fn () => '<?xml version="1.0"?><gesmes:Envelope xmlns:gesmes="http://www.gesmes.org/xml/2002-08-01" xmlns="http://www.ecb.int/vocabulary/2002-08-01/eurofxref"><Cube><Cube time="2026-10-30"><Cube currency="USD" rate="1.08"/><Cube currency="INR" rate="90"/></Cube></Cube></gesmes:Envelope>'));
        $db->run("INSERT INTO market_prices (store_id, wc_product_id, currency, amount_minor, updated_by, updated_at) VALUES (1, 10, 'USD', 9900, 'user:1', NOW(6))");
        $prices = new PriceResolver($db, $rates, 'INR', rounding: 'whole');
        $this->assertSame(['minor' => 9900, 'kind' => 'market_price', 'approximate' => false], array_intersect_key($prices->price(1, 10, 0, 900000, 'USD', 'USD'), array_flip(['minor', 'kind', 'approximate'])));
        $conv = $prices->price(1, 11, 0, 899900, 'USD', 'INR');
        $this->assertSame('converted', $conv['kind']);
        $this->assertSame(10800, $conv['minor'], '$107.99 rounded up to $108');
        $this->assertTrue($conv['approximate'], 'charged in INR, so the USD figure is an estimate');
    }
}
