<?php

declare(strict_types=1);

namespace Commerce\Core\Shipping;

/**
 * One implementation per carrier or aggregator (DHL Express MyDHL API, FedEx, UPS, Shiprocket X …).
 * None is implemented yet: each needs an account, API credentials and a sandbox run before it is written.
 */
interface CarrierAdapter
{
    public function code(): string;

    /** @return list<Quote> throws on transport or API errors */
    public function rates(ParcelRequest $request): array;

    /** @return array{tracking_number: string, label_ref: string} */
    public function book(ParcelRequest $request, string $serviceCode, string $reference): array;

    /** @return list<array{event_key: string, status: string, description: ?string, location: ?string, occurred_at: \DateTimeImmutable}> */
    public function track(string $trackingNumber): array;

    public function cancel(string $trackingNumber): bool;
}
