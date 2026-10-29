<?php

declare(strict_types=1);

namespace Commerce\Hub;

use Commerce\Core\Market\Currency;

/** Plain PHP templates. Everything printed goes through e() unless it is markup built here. */
final class View
{
    public const STATUS = [
        // inventory
        'in' => ['In stock', 'ok'], 'low' => ['Low stock', 'warn'], 'out' => ['Out of stock', 'bad'],
        'shared' => ['Shared across brands', 'info'], 'exclusive' => ['One storefront', 'neutral'], 'unmapped' => ['Not linked yet', 'warn'],
        // payment
        'pending' => ['Awaiting payment', 'warn'], 'paid' => ['Paid', 'ok'], 'failed' => ['Payment failed', 'bad'], 'cancelled' => ['Cancelled', 'neutral'],
        'refunded' => ['Refunded', 'neutral'], 'partially_refunded' => ['Partly refunded', 'info'],
        // fulfilment
        'unfulfilled' => ['To process', 'warn'], 'processing' => ['Processing', 'info'], 'picked' => ['Picked', 'info'], 'packed' => ['Ready to dispatch', 'info'],
        'partially_shipped' => ['Partly shipped', 'info'], 'shipped' => ['Shipped', 'ok'], 'delivered' => ['Delivered', 'ok'],
        // shipments
        'draft' => ['No tracking yet', 'warn'], 'label_created' => ['Ready to dispatch', 'info'], 'dispatched' => ['Dispatched', 'info'], 'in_transit' => ['In transit', 'info'], 'exception' => ['Delivery problem', 'bad'],
        // returns
        'requested' => ['Awaiting items', 'warn'], 'received' => ['Awaiting inspection', 'warn'], 'inspected' => ['Inspected', 'info'], 'closed' => ['Closed', 'neutral'],
        'not_requested' => ['No refund yet', 'neutral'], 'not_required' => ['No refund needed', 'neutral'], 'succeeded' => ['Refunded', 'ok'],
        // reservations
        'held' => ['Held, awaiting payment', 'warn'], 'confirmed' => ['Reserved', 'ok'], 'partially_fulfilled' => ['Partly shipped', 'info'], 'fulfilled' => ['Shipped', 'ok'], 'released' => ['Released', 'neutral'], 'expired' => ['Hold expired', 'neutral'],
        // purchase orders
        'ordered' => ['Ordered', 'info'], 'partially_received' => ['Partly received', 'info'],
        // stores
        'connected' => ['Connected', 'ok'], 'not_connected' => ['Not connected', 'neutral'], 'paused' => ['Paused', 'neutral'],
    ];

    public const ATTENTION = [
        'unmapped_item' => 'An item on this order is not linked to stock',
        'paid_no_stock' => 'Paid, but the stock is no longer available',
        'paid_after_cancel' => 'Paid after the order was cancelled',
        'delivery_exception' => 'The carrier reported a delivery problem',
    ];

    public const ACTIONS = [
        'start_processing' => 'Start processing', 'mark_picked' => 'Mark as picked', 'mark_packed' => 'Mark as packed',
        'create_shipment' => 'Create shipment', 'mark_dispatched' => 'Mark as dispatched', 'mark_delivered' => 'Mark as delivered',
        'request_mark_paid' => 'Record offline payment', 'request_cancel' => 'Cancel order', 'resolve_attention' => 'Resolve the problem',
    ];

    public function __construct(private readonly string $dir, private readonly App $app)
    {
    }

    public function render(string $template, array $vars, ?string $layout = 'layout'): string
    {
        $content = $this->partial($template, $vars);
        // Signed-out pages never get the app shell.
        if ($layout === null || ($vars['bare'] ?? false) || $this->app->user === null) {
            return $content;
        }
        return $this->partial($layout, $vars + ['content' => $content]);
    }

    public function partial(string $template, array $vars = []): string
    {
        $file = $this->dir . '/' . $template . '.php';
        $v = $this;
        $app = $this->app;
        extract($vars, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    public static function e(mixed $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function money(int|string|null $minor, ?string $currency): string
    {
        if ($minor === null || $currency === null) {
            return '—';
        }
        return self::e(Currency::format((int) $minor, $currency));
    }

    public function date(?string $ts, bool $time = true): string
    {
        if ($ts === null || $ts === '') {
            return '—';
        }
        $d = new \DateTimeImmutable($ts, new \DateTimeZone('UTC'));
        $d = $d->setTimezone(new \DateTimeZone($this->app->env['DISPLAY_TZ'] ?? 'Asia/Kolkata'));
        return self::e($d->format($time ? 'j M Y, H:i' : 'j M Y'));
    }

    public function badge(?string $status): string
    {
        [$label, $tone] = self::STATUS[(string) $status] ?? [ucfirst(str_replace('_', ' ', (string) $status)), 'neutral'];
        return '<span class="badge badge-' . $tone . '">' . self::e($label) . '</span>';
    }

    public function brand(int|string|null $id): string
    {
        $b = $this->app->services->brands()[(int) $id] ?? null;
        return $b === null ? '—' : '<span class="brand-tag brand-' . self::e($b['code']) . '">' . self::e($b['name']) . '</span>';
    }

    public function csrf(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::e($this->app->csrf()) . '">';
    }

    /** Current path with query changes; null removes a key. */
    public function url(string $path, array $query = [], array $merge = []): string
    {
        $q = array_filter(array_merge($query, $merge), fn ($v) => $v !== null && $v !== '');
        return self::e($path . ($q ? '?' . http_build_query($q) : ''));
    }

    public function can(string $permission, ?int $brandId = null): bool
    {
        return $this->app->user !== null && $this->app->user->can($permission, $brandId, $brandId === null);
    }

    public function icon(string $name): string
    {
        $paths = [
            'overview' => 'M3 3h7v7H3zM14 3h7v4h-7zM14 11h7v10h-7zM3 14h7v7H3z',
            'orders' => 'M6 2h12l2 4v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6zM4 6h16M9 10a3 3 0 0 0 6 0',
            'inventory' => 'M21 8 12 3 3 8v8l9 5 9-5zM3 8l9 5 9-5M12 13v8',
            'products' => 'M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8zM7 7h.01',
            'shipments' => 'M1 4h14v12H1zM15 9h4l3 3v4h-7M5.5 19.5a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM18.5 19.5a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
            'returns' => 'M9 14 4 9l5-5M4 9h11a5 5 0 0 1 0 10h-4',
            'transfers' => 'M7 7h13l-4-4M17 17H4l4 4',
            'reports' => 'M4 20V10M10 20V4M16 20v-7M22 20H2',
            'team' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8',
            'settings' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z',
            'health' => 'M22 12h-4l-3 9L9 3l-3 9H2',
            'bell' => 'M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0',
            'search' => 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.3-4.3',
            'help' => 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01',
            'warehouse' => 'M3 21V8l9-5 9 5v13M7 21v-8h10v8M7 17h10',
            'menu' => 'M3 6h18M3 12h18M3 18h18',
        ];
        return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="' . ($paths[$name] ?? '') . '"/></svg>';
    }
}
