<?php

declare(strict_types=1);

namespace App\Auth;

use App\Shared\Http\HttpException;

final class AuthException extends \RuntimeException implements HttpException
{
    public function __construct(
        string $message,
        private readonly int $status = 400,
    ) {
        parent::__construct($message);
    }

    #[\Override]
    public function status(): int
    {
        return $this->status;
    }
}
