<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\ChangePassword;

final readonly class Command
{
    public function __construct(
        public int $userId,
        public string $currentPassword,
        public string $newPassword,
        public string $newPasswordConfirmation,
    ) {}
}
