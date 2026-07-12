<?php

declare(strict_types=1);

use App\Auth\Application\Port\CaptchaVerifier;
use App\Auth\Application\Port\PasswordHasher;
use App\Auth\Infrastructure\Security\NativePasswordHasher;
use App\Auth\Infrastructure\Security\NullCaptchaVerifier;
use App\Auth\Infrastructure\Security\SmartCaptchaVerifier;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use function DI\get;

/**
 * Привязки модуля Auth. Репозиторий/сервис/фабрика токенов — автовайринг.
 * Капча: пустой SMARTCAPTCHA_SERVER_KEY → NullCaptchaVerifier (dev/тесты).
 */
return [
    PasswordHasher::class => get(NativePasswordHasher::class),

    CaptchaVerifier::class => static function(ContainerInterface $c): CaptchaVerifier {
        $serverKey = $_ENV['SMARTCAPTCHA_SERVER_KEY'] ?? '';

        if (!is_string($serverKey) || '' === trim($serverKey)) {
            return new NullCaptchaVerifier();
        }

        return new SmartCaptchaVerifier(trim($serverKey), $c->get(LoggerInterface::class));
    },
];
