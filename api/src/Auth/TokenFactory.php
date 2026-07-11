<?php

declare(strict_types=1);

namespace App\Auth;

final readonly class TokenFactory
{
    public function create(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
