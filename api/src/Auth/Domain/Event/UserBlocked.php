<?php

declare(strict_types=1);

namespace App\Auth\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;

final readonly class UserBlocked implements DomainEvent
{
    public function __construct(
        private int $userId,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function eventName(): string
    {
        return 'auth.user.blocked';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function aggregateId(): string
    {
        return (string)$this->userId;
    }

    public function payload(): array
    {
        return ['userId' => $this->userId];
    }
}
