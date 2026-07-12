<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\SetUserStatus;

use App\Auth\Application\UserManagementPolicy;
use App\Auth\AuthRepository;
use App\Auth\Domain\Event\UserBlocked;
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
    ) {}

    /** @return array<string, mixed> */
    public function handle(Command $command): array
    {
        if (!in_array($command->status, ['active', 'blocked'], true)) {
            throw new HttpError('Unknown status.', 422);
        }

        return $this->unitOfWork->transactional(function() use ($command): array {
            $target = $this->users->managedUserById($command->targetId);

            if (null === $target) {
                throw new HttpError('User not found.', 404);
            }

            $this->policy->ensureCanManage($command->actorRole, (string)$target['role']);

            if ('blocked' === $command->status) {
                $this->policy->ensureNotSelf($command->actorId, $command->targetId);
                $this->policy->ensureNotLastActiveOwner($target, $this->users->countActiveOwners());
            }

            $this->users->setUserStatus($command->targetId, $command->status);

            if ('blocked' === $command->status) {
                // Блокировка отзывает активные сессии пользователя.
                $this->users->deleteAllAccessTokensForUser($command->targetId);
                $this->events->publish(new UserBlocked($command->targetId, $this->clock->now()));
            }

            $updated = $this->users->managedUserById($command->targetId);

            if (null === $updated) {
                throw new HttpError('User not found.', 404);
            }

            return $updated;
        });
    }
}
