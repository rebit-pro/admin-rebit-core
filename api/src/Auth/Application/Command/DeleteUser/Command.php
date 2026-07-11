<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\DeleteUser;

final readonly class Command
{
    public function __construct(
        public string $actorRole,
        public int $actorId,
        public int $targetId,
    ) {
    }
}
