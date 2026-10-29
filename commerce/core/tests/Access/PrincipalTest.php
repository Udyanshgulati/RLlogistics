<?php

declare(strict_types=1);

namespace Commerce\Core\Tests\Access;

use Commerce\Core\Access\Forbidden;
use Commerce\Core\Access\Principal;
use Commerce\Core\Access\Roles;
use Commerce\Core\Tests\Support\TestCase;

final class PrincipalTest extends TestCase
{
    public function testOwnerCanDoEverything(): void
    {
        $p = new Principal(1, 'Owner', 'o@example.test', [['role' => 'owner', 'brand_id' => null]]);
        foreach (array_keys(Roles::PERMISSIONS) as $perm) {
            $this->assertTrue($p->can($perm), $perm);
        }
        $this->assertNull($p->brandScope('orders.view'));
    }

    public function testBrandManagerSeesOnlyTheirBrand(): void
    {
        $p = new Principal(2, 'BM', 'bm@example.test', [['role' => 'brand_manager', 'brand_id' => 3]]);
        $this->assertTrue($p->can('orders.view', 3));
        $this->assertFalse($p->can('orders.view', 1));
        $this->assertFalse($p->can('orders.view'), 'not an all-brands permission');
        $this->assertTrue($p->can('orders.view', anyBrand: true), 'may open the Orders page');
        $this->assertSame([3], $p->brandScope('orders.view'));
        $this->assertFalse($p->can('orders.view_customer', 3), 'no customer details');
        $this->assertFalse($p->can('settings.manage', 3));
    }

    public function testWarehouseStaffCannotDoUnrestrictedAdjustmentsOrRefunds(): void
    {
        $p = new Principal(3, 'WH', 'wh@example.test', [['role' => 'warehouse', 'brand_id' => null]]);
        $this->assertTrue($p->can('inventory.adjust_basic'));
        $this->assertTrue($p->can('orders.fulfil'));
        $this->assertFalse($p->can('inventory.adjust'));
        $this->assertFalse($p->can('refunds.request'));
        $this->assertFalse($p->can('payments.mark_paid'));
        $this->assertFalse($p->can('orders.view_customer'));
        $e = $this->assertThrows(Forbidden::class, fn () => $p->require('mapping.manage'));
        $this->assertStringContains('Link storefront listings', $e->getMessage());
    }

    public function testRolesCombineAcrossAssignments(): void
    {
        $p = new Principal(4, 'Mixed', 'm@example.test', [['role' => 'support', 'brand_id' => null], ['role' => 'brand_manager', 'brand_id' => 1]]);
        $this->assertTrue($p->can('orders.view_customer', 2));
        $this->assertTrue($p->can('reports.sales', 1));
        $this->assertFalse($p->can('reports.sales', 2));
        $this->assertNull($p->brandScope('orders.view'));
        $this->assertSame([1], $p->brandScope('reports.sales'));
    }

    public function testFinanceAndMarketingAreLimited(): void
    {
        $f = new Principal(5, 'F', 'f@example.test', [['role' => 'finance', 'brand_id' => null]]);
        $this->assertTrue($f->can('refunds.request'));
        $this->assertFalse($f->can('inventory.adjust_basic'));
        $m = new Principal(6, 'M', 'm@example.test', [['role' => 'marketing', 'brand_id' => null]]);
        $this->assertTrue($m->can('reports.sales'));
        $this->assertFalse($m->can('orders.view'));
        $this->assertFalse($m->can('orders.view_customer'));
        $this->assertThrows(\InvalidArgumentException::class, fn () => new Principal(7, 'X', 'x@example.test', [['role' => 'god', 'brand_id' => null]]));
    }
}
