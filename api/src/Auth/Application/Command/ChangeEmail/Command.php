<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\ChangeEmail;

final readonly class Command
{
    public function __construct(
        public int $userId,
        public string $newEmail,
        public string $currentPassword,
    ) {}
}
