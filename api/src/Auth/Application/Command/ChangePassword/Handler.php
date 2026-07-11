<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\ChangePassword;

use App\Auth\Application\Port\PasswordHasher;
use App\Auth\AuthException;
use App\Auth\AuthRepository;
use App\Auth\Domain\Event\UserPasswordChanged;
use App\Auth\TokenFactory;
use App\Shared\Application\Event\EventPublisher;
use App\Shared\Application\Transaction\UnitOfWork;
use App\Shared\Domain\Clock\Clock;

final readonly class Handler
{
    public function __construct(
        private AuthRepository $users,
        private PasswordHasher $hasher,
        private TokenFactory $tokenFactory,
        private UnitOfWork $unitOfWork,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    /** @return array{token:string,expiresAt:string} */
    public function handle(Command $command): array
    {
        return $this->unitOfWork->transactional(function () use ($command): array {
            $user = $this->users->findUserById($command->userId);

            if (null === $user) {
                throw new AuthException('User not found.', 404);
            }

            if (!$this->hasher->verify($command->currentPassword, $user['password_hash'])) {
                throw new AuthException('Current password is incorrect.', 401);
            }

            $this->guardNewPassword($command, $user['password_hash']);

            // Смена пароля: обновить хэш → отозвать ВСЕ токены → выдать новый (консилиум).
            $this->users->updatePassword($command->userId, $this->hasher->hash($command->newPassword));
            $this->users->deleteAllAccessTokensForUser($command->userId);

            $token = $this->tokenFactory->create();
            $expiresAt = $this->clock->now()->modify('+24 hours');
            $this->users->storeAccessToken($this->tokenFactory->hash($token), $command->userId, $expiresAt);

            $this->events->publish(new UserPasswordChanged($command->userId, $this->clock->now()));

            return ['token' => $token, 'expiresAt' => $expiresAt->format(DATE_ATOM)];
        });
    }

    private function guardNewPassword(Command $command, string $currentHash): void
    {
        if (mb_strlen($command->newPassword) < 8) {
            throw new AuthException('New password must contain at least 8 characters.', 422);
        }

        if (!hash_equals($command->newPassword, $command->newPasswordConfirmation)) {
            throw new AuthException('Password confirmation does not match.', 422);
        }

        if ($this->hasher->verify($command->newPassword, $currentHash)) {
            throw new AuthException('New password must differ from the current one.', 422);
        }
    }
}
