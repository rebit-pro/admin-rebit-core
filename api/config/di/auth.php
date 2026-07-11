<?php

declare(strict_types=1);

use App\Auth\Application\Port\PasswordHasher;
use App\Auth\Infrastructure\Security\NativePasswordHasher;

use function DI\get;

/**
 * Привязки модуля Auth. Репозиторий/сервис/фабрика токенов — автовайринг.
 */
return [
    PasswordHasher::class => get(NativePasswordHasher::class),
];
