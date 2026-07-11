<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\ChangeUserRole;

use App\Auth\Application\UserManagementPolicy;
use App\Auth\AuthRepository;
use App\Auth\Domain\Event\UserRoleChanged;
use App\Shared\Application\Event\EventPublisher;
use App\Shared\Application\Transaction\UnitOfWork;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Http\Exception\HttpError;

final readonly class Handler
{
    public function __construct(
        private AuthRepository $users,
        private UserManagementPolicy $policy,
        private UnitOfWork $unitOfWork,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    /** @return array<string, mixed> */
    public function handle(Command $command): array
    {
        return $this->unitOfWork->transactional(function () use ($command): array {
            $target = $this->users->managedUserById($command->targetId);

            if (null === $target) {
                throw new HttpError('User not found.', 404);
            }

            $this->policy->ensureCanManage($command->actorRole, (string) $target['role']);
            $this->policy->ensureCanAssignRole($command->actorRole, $command->newRole);

            // Демоушен owner → проверка «последнего активного owner».
            if ('owner' === $target['role'] && 'owner' !== $command->newRole) {
                $this->policy->ensureNotLastActiveOwner($target, $this->users->countActiveOwners());
            }

            $this->users->updateUserRole($command->targetId, $command->newRole);
            $this->events->publish(new UserRoleChanged($command->targetId, $command->newRole, $this->clock->now()));

            /** @var array<string, mixed> $updated */
            $updated = $this->users->managedUserById($command->targetId);

            return $updated;
        });
    }
}
