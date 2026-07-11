<?php

declare(strict_types=1);

namespace App\Access\Application;

use App\Access\Domain\Permission;
use App\Access\Domain\RolePermissions;

/**
 * Решение о доступе: есть ли у роли право. Владелец семантики прав — контекст
 * Access (docs/02-domain.md §5). Проверка владельца ресурса (IDOR) — в хендлерах.
 */
final class AccessDecision
{
    public function isGranted(string $role, Permission $permission): bool
    {
        return in_array($permission, RolePermissions::forRole($role), true);
    }
}
