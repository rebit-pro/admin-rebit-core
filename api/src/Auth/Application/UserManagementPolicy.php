<?php

declare(strict_types=1);

namespace App\Auth\Application;

use App\Shared\Http\Exception\HttpError;

/**
 * Инварианты управления пользователями (docs/02-domain.md §5, docs/01-scenarios.md §2.1):
 * админ не трогает owner и не выдаёт роль owner; проверка «последнего owner» — в хендлерах.
 */
final class UserManagementPolicy
{
    public const ROLES = ['owner', 'admin', 'user'];

    public function ensureRoleIsValid(string $role): void
    {
        if (!in_array($role, self::ROLES, true)) {
            throw new HttpError(sprintf('Unknown role: "%s".', $role), 422);
        }
    }

    public function ensureCanAssignRole(string $actorRole, string $desiredRole): void
    {
        $this->ensureRoleIsValid($desiredRole);

        if ('owner' === $desiredRole && 'owner' !== $actorRole) {
            throw new HttpError('Only an owner can grant the owner role.', 403);
        }
    }

    public function ensureCanManage(string $actorRole, string $targetRole): void
    {
        if ('owner' === $targetRole && 'owner' !== $actorRole) {
            throw new HttpError('Only an owner can manage another owner.', 403);
        }
    }

    public function ensureNotSelf(int $actorId, int $targetId): void
    {
        if ($actorId === $targetId) {
            throw new HttpError('You cannot perform this action on your own account.', 409);
        }
    }

    /**
     * @param array<string, mixed> $target
     */
    public function ensureNotLastActiveOwner(array $target, int $activeOwners): void
    {
        $isActiveOwner = 'owner' === ($target['role'] ?? null) && 'active' === ($target['status'] ?? 'active');

        if ($isActiveOwner && $activeOwners <= 1) {
            throw new HttpError('Cannot remove the last active owner.', 409);
        }
    }
}
