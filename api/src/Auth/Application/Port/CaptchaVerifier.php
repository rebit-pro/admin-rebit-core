<?php

declare(strict_types=1);

namespace App\Auth\Application\Port;

interface CaptchaVerifier
{
    public function verify(?string $token, ?string $ip = null): bool;
}
