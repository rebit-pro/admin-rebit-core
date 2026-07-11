<?php

declare(strict_types=1);

namespace App\Access\Domain;

/**
 * Хардкод-маппинг «роль → права» для MVP-1 (docs/05-modules.md §4.2).
 * При вводе таблиц access_* заменяется на чтение из read-модели.
 */
final class RolePermissions
{
    /** @return list<Permission> */
    public static function forRole(string $role): array
    {
        return match ($role) {
            'owner' => Permission::cases(),
            'admin' => [
                Permission::UsersManage,
                Permission::CatalogManage,
                Permission::ElementsRead,
                Permission::ElementsWrite,
            ],
            'user' => [Permission::ElementsRead],
            default => [],
        };
    }
}
