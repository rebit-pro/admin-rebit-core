<?php

declare(strict_types=1);

namespace App\Shared\Http\Exception;

use App\Shared\Http\HttpException;

/**
 * Универсальное HTTP-исключение (сообщение + статус) для прикладных ошибок,
 * которым не нужен отдельный класс. Обрабатывается ErrorJsonMiddleware.
 */
final class HttpError extends \RuntimeException implements HttpException
{
    public function __construct(string $message, private readonly int $status = 400)
    {
        parent::__construct($message);
    }

    #[\Override]
    public function status(): int
    {
        return $this->status;
    }
}
