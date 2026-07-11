<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\ChangeEmail;

use App\Auth\Application\Port\PasswordHasher;
use App\Auth\AuthException;
use App\Auth\AuthRepository;
use App\Auth\Domain\Event\UserEmailChanged;
use App\Shared\Application\Event\EventPublisher;
use App\Shared\Application\Transaction\UnitOfWork;
use App\Shared\Domain\Clock\Clock;

final readonly class Handler
{
    public function __construct(
        private AuthRepository $users,
        private PasswordHasher $hasher,
        private UnitOfWork $unitOfWork,
        private EventPublisher $events,
        private Clock $clock,
    ) {}

    /** @return array<string, mixed> Обновлённое представление пользователя. */
    public function handle(Command $command): array
    {
        return $this->unitOfWork->transactional(function() use ($command): array {
            $email = $this->normalizeEmail($command->newEmail);
            $user = $this->users->findUserById($command->userId);

            if (null === $user) {
                throw new AuthException('User not found.', 404);
            }

            // Смена email требует подтверждения текущим паролем (docs/01-scenarios.md §1.6).
            if (!$this->hasher->verify($command->currentPassword, $user['password_hash'])) {
                throw new AuthException('Current password is incorrect.', 401);
            }

            if ($this->users->isEmailTaken($email, $command->userId)) {
                throw new AuthException('Email is already taken.', 409);
            }

            $this->users->updateEmail($command->userId, $email);
            $this->events->publish(new UserEmailChanged($command->userId, $email, $this->clock->now()));

            $updated = $this->users->findUserById($command->userId);

            return [
                'id' => $updated['id'] ?? null,
                'email' => $updated['email'] ?? null,
                'name' => $updated['name'] ?? null,
                'login' => $updated['login'] ?? null,
                'role' => $updated['role'] ?? null,
            ];
        });
    }

    private function normalizeEmail(string $email): string
    {
        $email = trim($email);

        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new AuthException('Email is invalid.', 422);
        }

        return mb_strtolower($email);
    }
}
