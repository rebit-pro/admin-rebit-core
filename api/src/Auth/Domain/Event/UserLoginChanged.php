<?php

declare(strict_types=1);

namespace App\Auth\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;

final readonly class UserLoginChanged implements DomainEvent
{
    public function __construct(
        private int $userId,
        private string $newLogin,
        private \DateTimeImmutable $occurredAt,
    ) {}

    #[\Override]
    public function eventName(): string
    {
        return 'auth.user.login_changed';
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
        return ['userId' => $this->userId, 'newLogin' => $this->newLogin];
    }
}
