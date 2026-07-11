<?php

declare(strict_types=1);

namespace App\Auth\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use DateTimeImmutable;

final readonly class UserEmailChanged implements DomainEvent
{
    public function __construct(
        private int $userId,
        private string $newEmail,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function eventName(): string
    {
        return 'auth.user.email_changed';
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function aggregateId(): string
    {
        return (string) $this->userId;
    }

    public function payload(): array
    {
        return ['userId' => $this->userId, 'newEmail' => $this->newEmail];
    }
}
