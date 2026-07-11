<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Security;

use App\Auth\Application\Port\PasswordHasher;

/**
 * argon2id, если доступен в сборке PHP, иначе bcrypt (PASSWORD_DEFAULT).
 * verify() совместим со старыми bcrypt-хэшами фикстур.
 */
final class NativePasswordHasher implements PasswordHasher
{
    public function hash(string $plain): string
    {
        $algo = \defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;

        return password_hash($plain, $algo);
    }

    public function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }
}
