<?php

declare(strict_types=1);

namespace Commerce\Core\Shipping;

/**
 * Turns carrier quotes into the options shown at checkout.
 *
 * Only express-level quotes are offered unless the business allows a non-express fallback, and then only
 * labelled as such: an express order is never silently downgraded. If carriers fail or return nothing,
 * the result says so; no price or date is invented. Dates are "estimated" unless the carrier guarantees
 * them and the business chose to present guarantees.
 */
final class ExpressSelector
{
    public function __construct(private readonly bool $allowNonExpressFallback = false, private readonly bool $presentGuarantees = false)
    {
    }

    /**
     * @param list<CarrierAdapter> $adapters
     * @return array{status: string, options: list<array>, message: ?string, errors: list<string>}
     */
    public function options(array $adapters, ParcelRequest $request): array
    {
        if ($problems = $request->problems()) {
            return ['status' => 'incomplete', 'options' => [], 'message' => 'Shipping cannot be calculated yet.', 'errors' => $problems];
        }
        $quotes = [];
        $errors = [];
        foreach ($adapters as $a) {
            try {
                array_push($quotes, ...$a->rates($request));
            } catch (\Throwable $e) {
                $errors[] = $a->code() . ': ' . $e->getMessage();
            }
        }
        $express = array_values(array_filter($quotes, fn (Quote $q) => $q->level === 'express'));
        if ($express !== []) {
            return ['status' => 'express', 'options' => self::present($express, $this->presentGuarantees), 'message' => null, 'errors' => $errors];
        }
        if ($quotes === [] && $errors !== []) {
            return ['status' => 'rates_unavailable', 'options' => [], 'message' => 'Live shipping rates are unavailable right now. Please try again in a few minutes.', 'errors' => $errors];
        }
        $others = array_values(array_filter($quotes, fn (Quote $q) => $q->level !== 'express'));
        if ($this->allowNonExpressFallback && $others !== []) {
            return ['status' => 'non_express_only', 'options' => self::present($others, $this->presentGuarantees), 'message' => 'Express delivery is not available to this address. These slower services are available instead.', 'errors' => $errors];
        }
        return ['status' => 'express_unavailable', 'options' => [], 'message' => 'Express delivery is not available to this address yet. You can change the delivery address or contact us.', 'errors' => $errors];
    }

    private static function present(array $quotes, bool $presentGuarantees): array
    {
        usort($quotes, fn (Quote $a, Quote $b) => [$a->currency, $a->amountMinor] <=> [$b->currency, $b->amountMinor]);
        return array_map(fn (Quote $q) => [
            'carrier' => $q->carrier,
            'service_code' => $q->serviceCode,
            'service_name' => $q->serviceName,
            'level' => $q->level,
            'amount_minor' => $q->amountMinor,
            'currency' => $q->currency,
            'delivery_date' => $q->estimatedDelivery?->format('Y-m-d'),
            'date_label' => $q->estimatedDelivery === null ? null : ($q->carrierGuaranteed && $presentGuarantees ? 'Guaranteed by' : 'Estimated'),
            'surcharges' => $q->surcharges,
        ], $quotes);
    }
}
