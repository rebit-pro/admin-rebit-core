<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Security;

use App\Auth\Application\Port\CaptchaVerifier;

/**
 * Проверка выключена (пустой SMARTCAPTCHA_SERVER_KEY): dev, тесты, e2e-моки.
 */
final readonly class NullCaptchaVerifier implements CaptchaVerifier
{
    #[\Override]
    public function verify(?string $token, ?string $ip = null): bool
    {
        return true;
    }
}
