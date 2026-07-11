<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\ChangeUserRole;

final readonly class Command
{
    public function __construct(
        public string $actorRole,
        public int $targetId,
        public string $newRole,
    ) {
    }
}
