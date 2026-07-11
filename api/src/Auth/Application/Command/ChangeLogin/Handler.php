<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\ChangeLogin;

use App\Auth\AuthException;
use App\Auth\AuthRepository;
use App\Auth\Domain\Event\UserLoginChanged;
use App\Shared\Application\Event\EventPublisher;
use App\Shared\Application\Transaction\UnitOfWork;
use App\Shared\Domain\Clock\Clock;

final readonly class Handler
{
    public function __construct(
        private AuthRepository $users,
        private UnitOfWork $unitOfWork,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    /** @return array<string, mixed> Обновлённое представление пользователя. */
    public function handle(Command $command): array
    {
        return $this->unitOfWork->transactional(function () use ($command): array {
            $login = $this->normalizeLogin($command->login);

            if (null === $this->users->findUserById($command->userId)) {
                throw new AuthException('User not found.', 404);
            }

            if ($this->users->isLoginTaken($login, $command->userId)) {
                throw new AuthException('Login is already taken.', 409);
            }

            $this->users->updateLogin($command->userId, $login);
            $this->events->publish(new UserLoginChanged($command->userId, $login, $this->clock->now()));

            $updated = $this->users->findUserById($command->userId);

            return $this->view($updated ?? []);
        });
    }

    private function normalizeLogin(string $login): string
    {
        $login = trim($login);

        if (1 !== preg_match('/^[a-z0-9][a-z0-9._-]{2,31}$/i', $login)) {
            throw new AuthException('Login must be 3–32 characters: latin letters, digits, and . _ -', 422);
        }

        return $login;
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function view(array $user): array
    {
        return [
            'id' => $user['id'] ?? null,
            'email' => $user['email'] ?? null,
            'name' => $user['name'] ?? null,
            'login' => $user['login'] ?? null,
            'role' => $user['role'] ?? null,
            'phone' => $user['phone'] ?? null,
            'address' => $user['address'] ?? null,
        ];
    }
}
