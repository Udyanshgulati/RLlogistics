<?php

declare(strict_types=1);

namespace Commerce\Hub;

use Commerce\Core\Catalog\Catalog;
use Commerce\Core\Catalog\SkuPolicy;
use Commerce\Core\Integration\Outbox;
use Commerce\Core\Integration\StockPushHandler;
use Commerce\Core\Integration\StoreClient;
use Commerce\Core\Integration\StoreRequestHandlers;
use Commerce\Core\Integration\WooCommerceClient;
use Commerce\Core\Inventory\InventoryCsv;
use Commerce\Core\Inventory\InventoryView;
use Commerce\Core\Inventory\Ledger;
use Commerce\Core\Inventory\Purchasing;
use Commerce\Core\Market\MarketConfig;
use Commerce\Core\Orders\Fulfilment;
use Commerce\Core\Orders\OrderSync;
use Commerce\Core\Orders\Returns;
use Commerce\Core\Support\Audit;

/** Lazily built core services, one instance per request. */
final class Services
{
    private array $made = [];
    public ?StoreClient $storeClient = null;

    public function __construct(private readonly App $app)
    {
    }

    private function once(string $key, callable $make): mixed
    {
        return $this->made[$key] ??= $make();
    }

    public function ledger(): Ledger
    {
        return $this->once('ledger', fn () => new Ledger($this->app->db, $this->app->clock));
    }

    public function audit(): Audit
    {
        return $this->once('audit', fn () => new Audit($this->app->db, $this->app->clock));
    }

    public function catalog(): Catalog
    {
        return $this->once('catalog', fn () => new Catalog($this->app->db, $this->ledger(), $this->skuPolicy(), $this->audit(), $this->app->clock));
    }

    public function skuPolicy(): SkuPolicy
    {
        return $this->once('sku', fn () => new SkuPolicy());
    }

    public function inventory(): InventoryView
    {
        return $this->once('inv', fn () => new InventoryView($this->app->db));
    }

    public function csv(): InventoryCsv
    {
        return $this->once('csv', fn () => new InventoryCsv($this->app->db, $this->ledger()));
    }

    public function purchasing(): Purchasing
    {
        return $this->once('po', fn () => new Purchasing($this->app->db, $this->ledger(), $this->audit(), $this->app->clock));
    }

    public function sync(): OrderSync
    {
        return $this->once('sync', fn () => new OrderSync($this->app->db, $this->ledger(), $this->app->clock));
    }

    public function fulfilment(): Fulfilment
    {
        return $this->once('ful', fn () => new Fulfilment($this->app->db, $this->ledger(), $this->audit(), $this->app->clock));
    }

    public function returns(): Returns
    {
        return $this->once('ret', fn () => new Returns($this->app->db, $this->ledger(), $this->audit(), $this->app->clock));
    }

    public function store(): StoreClient
    {
        return $this->storeClient ??= new WooCommerceClient($this->app->db);
    }

    public function outbox(array $extraHandlers = []): Outbox
    {
        return new Outbox($this->app->db, $this->app->clock, ['stock.push' => new StockPushHandler($this->app->db, $this->store())]
            + (new StoreRequestHandlers($this->app->db, $this->store(), $this->returns()))->handlers() + $extraHandlers);
    }

    public function markets(): MarketConfig
    {
        return $this->once('markets', fn () => MarketConfig::fromFile($this->app->env['MARKETS_CONFIG'] ?? __DIR__ . '/../../config/markets.example.json'));
    }

    public function view(): View
    {
        return $this->once('view', fn () => new View(__DIR__ . '/../templates', $this->app));
    }

    /** @return array<int, array{id: int, code: string, name: string}> */
    public function brands(): array
    {
        return $this->once('brands', fn () => array_column($this->app->db->all('SELECT id, code, name FROM brands ORDER BY id'), null, 'id'));
    }
}
