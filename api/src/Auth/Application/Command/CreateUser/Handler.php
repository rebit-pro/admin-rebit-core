<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\CreateUser;

use App\Auth\Application\Port\PasswordHasher;
use App\Auth\Application\UserManagementPolicy;
use App\Auth\AuthRepository;
use App\Auth\Domain\Event\UserCreated;
use App\Shared\Application\Event\EventPublisher;
use App\Shared\Application\Transaction\UnitOfWork;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Http\Exception\HttpError;

final readonly class Handler
{
    public function __construct(
        private AuthRepository $users,
        private PasswordHasher $hasher,
        private UserManagementPolicy $policy,
        private UnitOfWork $unitOfWork,
        private EventPublisher $events,
        private Clock $clock,
    ) {}

    /** @return array<string, mixed> */
    public function handle(Command $command): array
    {
        return $this->unitOfWork->transactional(function() use ($command): array {
            $this->policy->ensureCanAssignRole($command->actorRole, $command->role);

            $email = $this->email($command->email);
            $login = $this->login($command->login);
            $name = trim($command->name);

            if ('' === $name) {
                throw new HttpError('Name is required.', 422);
            }

            if (mb_strlen($command->password) < 8) {
                throw new HttpError('Password must contain at least 8 characters.', 422);
            }

            if ($this->users->isEmailTaken($email, 0)) {
                throw new HttpError('Email is already taken.', 409);
            }

            if ($this->users->isLoginTaken($login, 0)) {
                throw new HttpError('Login is already taken.', 409);
            }

            $id = $this->users->createManagedUser(
                $email,
                $this->hasher->hash($command->password),
                $name,
                $login,
                $command->role,
                $this->nullableString($command->phone),
                $this->nullableString($command->address),
            );

            $this->events->publish(new UserCreated($id, $command->role, $this->clock->now()));

            $created = $this->users->managedUserById($id);

            if (null === $created) {
                throw new HttpError('User not found.', 404);
            }

            return $created;
        });
    }

    private function email(string $email): string
    {
        $email = trim($email);

        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new HttpError('Email is invalid.', 422);
        }

        return mb_strtolower($email);
    }

    private function login(string $login): string
    {
        $login = trim($login);

        if (1 !== preg_match('/^[a-z0-9][a-z0-9._-]{2,31}$/i', $login)) {
            throw new HttpError('Login must be 3–32 characters: latin letters, digits, and . _ -', 422);
        }

        return $login;
    }

    private function nullableString(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
