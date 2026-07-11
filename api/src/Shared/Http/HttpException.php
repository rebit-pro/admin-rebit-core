<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Throwable;

/**
 * Исключение, несущее HTTP-статус. Обрабатывается ErrorJsonMiddleware.
 * Доменные исключения модулей (напр. AuthException) реализуют этот интерфейс.
 */
interface HttpException extends Throwable
{
    public function status(): int;
}
