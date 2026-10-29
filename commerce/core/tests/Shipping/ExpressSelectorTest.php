<?php

declare(strict_types=1);

namespace Commerce\Core\Tests\Shipping;

use Commerce\Core\Shipping\CarrierAdapter;
use Commerce\Core\Shipping\ExpressSelector;
use Commerce\Core\Shipping\ParcelRequest;
use Commerce\Core\Shipping\Quote;
use Commerce\Core\Tests\Support\TestCase;

final class ExpressSelectorTest extends TestCase
{
    private function request(string $dest = 'GB', ?string $hs = '610910', array $origin = ['country' => 'IN', 'postcode' => 'TEST-PIN']): ParcelRequest
    {
        return new ParcelRequest($origin, ['country' => $dest], 600, [300, 250, 40], [['sku' => 'MI-TEE-001-M', 'qty' => 1, 'weight_g' => 600, 'value_minor' => 149900, 'currency' => 'INR', 'hs_code' => $hs, 'origin_country' => 'IN']]);
    }

    private function adapter(array|\Throwable $result, string $code = 'fixture'): CarrierAdapter
    {
        return new class ($result, $code) implements CarrierAdapter {
            public function __construct(private array|\Throwable $r, private string $c) {}
            public function code(): string { return $this->c; }
            public function rates(ParcelRequest $request): array { if ($this->r instanceof \Throwable) { throw $this->r; } return $this->r; }
            public function book(ParcelRequest $request, string $serviceCode, string $reference): array { return []; }
            public function track(string $trackingNumber): array { return []; }
            public function cancel(string $trackingNumber): bool { return false; }
        };
    }

    // Fixture quotes: shapes as an adapter would map them; the numbers are not real carrier rates.
    private function quotes(): array
    {
        return [
            new Quote('fixture-a', 'X1', 'Express 1', 'express', 400000, 'INR', new \DateTimeImmutable('2026-11-05'), carrierGuaranteed: true),
            new Quote('fixture-a', 'E1', 'Economy', 'economy', 150000, 'INR', new \DateTimeImmutable('2026-11-15')),
            new Quote('fixture-b', 'X2', 'Express 2', 'express', 380000, 'INR'),
        ];
    }

    public function testOnlyExpressServicesAreOfferedCheapestFirstWithEstimatedDates(): void
    {
        $r = (new ExpressSelector())->options([$this->adapter($this->quotes())], $this->request());
        $this->assertSame('express', $r['status']);
        $this->assertSame(['X2', 'X1'], array_column($r['options'], 'service_code'));
        $this->assertSame([null, 'Estimated'], array_column($r['options'], 'date_label'), 'guarantee not presented unless the business opts in');
        $this->assertSame('Guaranteed by', (new ExpressSelector(presentGuarantees: true))->options([$this->adapter($this->quotes())], $this->request())['options'][1]['date_label']);
    }

    public function testNoExpressServiceIsNeverSilentlyDowngraded(): void
    {
        $economy = [new Quote('fixture-a', 'E1', 'Economy', 'economy', 150000, 'INR')];
        $r = (new ExpressSelector())->options([$this->adapter($economy)], $this->request('NZ'));
        $this->assertSame('express_unavailable', $r['status']);
        $this->assertSame([], $r['options']);
        $fallback = (new ExpressSelector(allowNonExpressFallback: true))->options([$this->adapter($economy)], $this->request('NZ'));
        $this->assertSame('non_express_only', $fallback['status']);
        $this->assertStringContains('slower', $fallback['message']);
    }

    public function testCarrierFailureNeverProducesAPrice(): void
    {
        $r = (new ExpressSelector())->options([$this->adapter(new \RuntimeException('timeout'), 'dhl')], $this->request());
        $this->assertSame('rates_unavailable', $r['status']);
        $this->assertSame([], $r['options']);
        $this->assertSame(['dhl: timeout'], $r['errors']);
        // One carrier down, another answering: use the answer.
        $mixed = (new ExpressSelector())->options([$this->adapter(new \RuntimeException('timeout'), 'dhl'), $this->adapter($this->quotes())], $this->request());
        $this->assertSame('express', $mixed['status']);
    }

    public function testMissingWarehouseAddressOrCustomsDataStopsQuoting(): void
    {
        $r = (new ExpressSelector())->options([$this->adapter($this->quotes())], $this->request(origin: ['country' => 'IN']));
        $this->assertSame('incomplete', $r['status']);
        $this->assertContains('The warehouse address is not configured.', $r['errors']);
        $this->assertSame('incomplete', (new ExpressSelector())->options([$this->adapter($this->quotes())], $this->request(hs: null))['status']);
        $this->assertSame('express', (new ExpressSelector())->options([$this->adapter($this->quotes())], $this->request('IN', hs: null))['status'], 'domestic needs no HS code');
    }
}
