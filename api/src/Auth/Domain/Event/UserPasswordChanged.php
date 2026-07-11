<?php

declare(strict_types=1);

namespace App\Auth\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;

final readonly class UserPasswordChanged implements DomainEvent
{
    public function __construct(
        private int $userId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    #[\Override]
    public function eventName(): string
    {
        return 'auth.user.password_changed';
    }

    #[\Override]
    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    #[\Override]
    public function aggregateId(): string
    {
        return (string)$this->userId;
    }

    #[\Override]
    public function payload(): array
    {
        // redaction: без хэшей/токенов/паролей (docs/01-scenarios.md §6.9)
        return ['userId' => $this->userId];
    }
}
