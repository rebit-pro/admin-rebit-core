<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\ChangeLogin;

final readonly class Command
{
    public function __construct(
        public int $userId,
        public string $login,
    ) {}
}
