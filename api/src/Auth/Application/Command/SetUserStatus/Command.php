<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\SetUserStatus;

final readonly class Command
{
    public function __construct(
        public string $actorRole,
        public int $actorId,
        public int $targetId,
        public string $status,
    ) {}
}
