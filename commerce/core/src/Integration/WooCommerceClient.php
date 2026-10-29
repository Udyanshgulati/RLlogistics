<?php

declare(strict_types=1);

namespace Commerce\Core\Integration;

use Commerce\Core\Db\Database;

/**
 * WooCommerce REST API v3 client. Status: written against the documented API, not yet run against a
 * live store. It must pass the staging integration checklist (docs/test-plan.md) before production use.
 *
 * Credentials come from the environment, never the database: STORE_<CODE>_WC_KEY / STORE_<CODE>_WC_SECRET
 * (CODE = the store code upper-cased, hyphens as underscores). Use a key with read/write on
 * products and orders only, created for a dedicated "hub" WordPress user.
 */
final class WooCommerceClient implements StoreClient
{
    /** @param callable(string, string, array, ?array, array): array{int, array|null} $transport for tests; defaults to curl */
    public function __construct(private readonly Database $db, private readonly int $timeoutSeconds = 15, private $transport = null)
    {
    }

    public function orderStatus(int $storeId, int $wcOrderId): ?string
    {
        try {
            return (string) ($this->call($storeId, 'GET', "orders/{$wcOrderId}", null)['status'] ?? null) ?: null;
        } catch (PermanentFailure $e) {
            if (str_contains($e->getMessage(), 'answered 404')) {
                return null;
            }
            throw $e;
        }
    }

    public function setStock(int $storeId, int $wcProductId, int $wcVariationId, int $quantity): void
    {
        $path = $wcVariationId > 0 ? "products/{$wcProductId}/variations/{$wcVariationId}" : "products/{$wcProductId}";
        $this->call($storeId, 'PUT', $path, ['manage_stock' => true, 'stock_quantity' => $quantity]);
    }

    public function setOrderStatus(int $storeId, int $wcOrderId, string $status, string $note): void
    {
        $this->call($storeId, 'PUT', "orders/{$wcOrderId}", ['status' => $status]);
        $this->call($storeId, 'POST', "orders/{$wcOrderId}/notes", ['note' => $note, 'customer_note' => false]);
    }

    public function addShipmentNote(int $storeId, int $wcOrderId, string $note): void
    {
        $this->call($storeId, 'POST', "orders/{$wcOrderId}/notes", ['note' => $note, 'customer_note' => true]);
    }

    public function refund(int $storeId, int $wcOrderId, string $amount, string $reason): string
    {
        // api_refund=true asks WooCommerce to refund through the order's payment gateway.
        $r = $this->call($storeId, 'POST', "orders/{$wcOrderId}/refunds", ['amount' => $amount, 'reason' => $reason, 'api_refund' => true]);
        return (string) ($r['id'] ?? throw new \RuntimeException('The storefront did not return a refund id.'));
    }

    public function findRefund(int $storeId, int $wcOrderId, string $marker): ?string
    {
        foreach ($this->call($storeId, 'GET', "orders/{$wcOrderId}/refunds", null) as $r) {
            if (str_contains((string) ($r['reason'] ?? ''), $marker)) {
                return (string) $r['id'];
            }
        }
        return null;
    }

    private function call(int $storeId, string $method, string $path, ?array $body): array
    {
        $store = $this->db->one('SELECT code, base_url FROM stores WHERE id = ?', [$storeId]) ?? throw new PermanentFailure('Unknown store.');
        if ($store['base_url'] === null || !str_starts_with($store['base_url'], 'https://')) {
            throw new PermanentFailure("The {$store['code']} storefront has no HTTPS address configured yet.");
        }
        $env = 'STORE_' . strtoupper(str_replace('-', '_', $store['code']));
        $key = getenv($env . '_WC_KEY');
        $secret = getenv($env . '_WC_SECRET');
        if (!$key || !$secret) {
            throw new PermanentFailure("API credentials for the {$store['code']} storefront are not configured ({$env}_WC_KEY).");
        }
        $url = rtrim($store['base_url'], '/') . '/wp-json/wc/v3/' . $path;
        $headers = ['Authorization: Basic ' . base64_encode("{$key}:{$secret}"), 'Accept: application/json', 'Content-Type: application/json'];
        [$status, $json] = ($this->transport ?? $this->curl(...))($method, $url, $headers, $body, ['timeout' => $this->timeoutSeconds]);
        if ($status >= 200 && $status < 300) {
            return $json ?? [];
        }
        $msg = "The {$store['code']} storefront answered {$status} for {$method} {$path}" . (isset($json['message']) ? ': ' . strip_tags((string) $json['message']) : '');
        // 4xx other than timeouts/rate limits will not fix themselves.
        if ($status >= 400 && $status < 500 && !in_array($status, [408, 409, 425, 429], true)) {
            throw new PermanentFailure($msg);
        }
        throw new \RuntimeException($msg);
    }

    /** @return array{int, array|null} */
    private function curl(string $method, string $url, array $headers, ?array $body, array $opts): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $opts['timeout'],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            throw new \RuntimeException("Could not reach the storefront: {$err}");
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $json = json_decode((string) $raw, true);
        return [$status, is_array($json) ? $json : null];
    }
}
