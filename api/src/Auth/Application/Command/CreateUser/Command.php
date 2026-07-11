<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\CreateUser;

final readonly class Command
{
    public function __construct(
        public string $actorRole,
        public string $email,
        public string $password,
        public string $name,
        public string $login,
        public string $role,
        public ?string $phone = null,
        public ?string $address = null,
    ) {}
}
