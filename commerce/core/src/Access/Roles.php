<?php

declare(strict_types=1);

namespace Commerce\Core\Access;

/**
 * Roles and the permissions each one grants. A role is assigned for all brands or for one brand.
 * Permission checks happen on the server for every request; hiding a button is only a convenience.
 */
final class Roles
{
    public const LABELS = [
        'owner' => 'Owner / Super admin',
        'ops_manager' => 'Operations manager',
        'inventory_manager' => 'Inventory manager',
        'warehouse' => 'Warehouse staff',
        'brand_manager' => 'Brand manager',
        'support' => 'Customer support',
        'finance' => 'Finance',
        'marketing' => 'Marketing',
    ];

    public const PERMISSIONS = [
        'overview.view' => 'See the overview',
        'inventory.view' => 'See stock levels and history',
        'inventory.adjust' => 'Record any stock adjustment, stocktake or write-off',
        'inventory.adjust_basic' => 'Record stock received, damaged and returned-to-inventory adjustments',
        'inventory.import' => 'Import and export inventory CSV files',
        'products.view' => 'See products and SKUs',
        'products.manage' => 'Create products, sizes and reorder levels',
        'mapping.manage' => 'Link storefront listings to stock (decides shared inventory)',
        'purchasing.manage' => 'Create suppliers and purchase orders',
        'purchasing.receive' => 'Receive purchase order deliveries',
        'orders.view' => 'See orders',
        'orders.view_customer' => 'See customer names, emails, phone numbers and addresses',
        'orders.fulfil' => 'Pick, pack, create shipments and dispatch',
        'orders.request_cancel' => 'Ask the storefront to cancel an order',
        'payments.mark_paid' => 'Record an offline payment (sent to the storefront for confirmation)',
        'returns.open' => 'Open returns and exchanges',
        'returns.process' => 'Receive and inspect returned items',
        'refunds.request' => 'Request refunds through the storefront',
        'reports.operations' => 'See inventory and fulfilment reports',
        'reports.sales' => 'See sales reports (no customer details)',
        'reports.finance' => 'See payment, refund and currency reports',
        'integrations.view' => 'See integration health and failed events',
        'integrations.retry' => 'Retry failed events and run reconciliation',
        'team.manage' => 'Invite people and assign roles',
        'settings.manage' => 'Change settings, stores, carriers and gateways',
    ];

    private const GRANTS = [
        'owner' => ['*'],
        'ops_manager' => ['overview.view', 'inventory.view', 'inventory.adjust', 'inventory.adjust_basic', 'inventory.import', 'products.view', 'products.manage', 'mapping.manage',
            'purchasing.manage', 'purchasing.receive', 'orders.view', 'orders.view_customer', 'orders.fulfil', 'orders.request_cancel', 'returns.open', 'returns.process',
            'reports.operations', 'reports.sales', 'integrations.view', 'integrations.retry'],
        'inventory_manager' => ['overview.view', 'inventory.view', 'inventory.adjust', 'inventory.adjust_basic', 'inventory.import', 'products.view', 'products.manage', 'mapping.manage',
            'purchasing.manage', 'purchasing.receive', 'orders.view', 'reports.operations', 'integrations.view'],
        'warehouse' => ['overview.view', 'inventory.view', 'inventory.adjust_basic', 'products.view', 'purchasing.receive', 'orders.view', 'orders.fulfil', 'returns.process'],
        'brand_manager' => ['overview.view', 'inventory.view', 'products.view', 'orders.view', 'reports.operations', 'reports.sales'],
        'support' => ['overview.view', 'orders.view', 'orders.view_customer', 'orders.request_cancel', 'returns.open', 'products.view', 'inventory.view'],
        'finance' => ['overview.view', 'orders.view', 'orders.view_customer', 'payments.mark_paid', 'refunds.request', 'reports.sales', 'reports.finance'],
        'marketing' => ['overview.view', 'products.view', 'reports.sales'],
    ];

    /** @return list<string> */
    public static function permissionsOf(string $role): array
    {
        $g = self::GRANTS[$role] ?? throw new \InvalidArgumentException("Unknown role {$role}.");
        return $g === ['*'] ? array_keys(self::PERMISSIONS) : $g;
    }

    public static function exists(string $role): bool
    {
        return isset(self::GRANTS[$role]);
    }
}
