<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\DeleteUser;

use App\Auth\Application\UserManagementPolicy;
use App\Auth\AuthRepository;
use App\Auth\Domain\Event\UserDeleted;
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

    public function handle(Command $command): void
    {
        $this->unitOfWork->transactional(function() use ($command): void {
            $target = $this->users->managedUserById($command->targetId);

            if (null === $target) {
                throw new HttpError('User not found.', 404);
            }

            $this->policy->ensureCanManage($command->actorRole, (string)$target['role']);
            $this->policy->ensureNotSelf($command->actorId, $command->targetId);
            $this->policy->ensureNotLastActiveOwner($target, $this->users->countActiveOwners());

            // FK auth_access_tokens.user_id ON DELETE CASCADE снимает токены.
            $this->users->deleteUser($command->targetId);
            $this->events->publish(new UserDeleted($command->targetId, $this->clock->now()));
        });
    }
}
