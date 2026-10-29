<?php

declare(strict_types=1);

namespace Commerce\Core\Access;

use Commerce\Core\Db\Database;

/** The signed-in person and what they may do, per brand. */
final class Principal
{
    /** @param list<array{role: string, brand_id: int|null}> $assignments */
    public function __construct(public readonly int $userId, public readonly string $name, public readonly string $email, private readonly array $assignments)
    {
        foreach ($assignments as $a) {
            if (!Roles::exists($a['role'])) {
                throw new \InvalidArgumentException("Unknown role {$a['role']}.");
            }
        }
    }

    public static function load(Database $db, int $userId): ?self
    {
        $u = $db->one("SELECT * FROM users WHERE id = ? AND status = 'active'", [$userId]);
        if ($u === null) {
            return null;
        }
        $roles = array_map(
            fn ($r) => ['role' => $r['role'], 'brand_id' => $r['brand_id'] === null ? null : (int) $r['brand_id']],
            $db->all('SELECT role, brand_id FROM user_roles WHERE user_id = ?', [$userId]),
        );
        return new self((int) $u['id'], $u['name'], $u['email'], $roles);
    }

    public function actor(): string
    {
        return 'user:' . $this->userId;
    }

    /**
     * True when a role grants $permission for all brands, or for $brandId.
     * With $brandId null, a brand-scoped role is enough only for $anyBrand checks (e.g. "may open the Orders page").
     */
    public function can(string $permission, ?int $brandId = null, bool $anyBrand = false): bool
    {
        foreach ($this->assignments as $a) {
            if (!in_array($permission, Roles::permissionsOf($a['role']), true)) {
                continue;
            }
            if ($a['brand_id'] === null || ($brandId !== null && $a['brand_id'] === $brandId) || ($brandId === null && $anyBrand)) {
                return true;
            }
        }
        return false;
    }

    /** @return list<int>|null brands whose data this permission covers; null = every brand */
    public function brandScope(string $permission): ?array
    {
        $brands = [];
        foreach ($this->assignments as $a) {
            if (in_array($permission, Roles::permissionsOf($a['role']), true)) {
                if ($a['brand_id'] === null) {
                    return null;
                }
                $brands[] = $a['brand_id'];
            }
        }
        return array_values(array_unique($brands));
    }

    /** @return list<array{role: string, brand_id: int|null}> */
    public function assignments(): array
    {
        return $this->assignments;
    }

    public function require(string $permission, ?int $brandId = null, bool $anyBrand = false): void
    {
        if (!$this->can($permission, $brandId, $anyBrand)) {
            throw new Forbidden($permission);
        }
    }
}
