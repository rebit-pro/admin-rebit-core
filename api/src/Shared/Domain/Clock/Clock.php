<?php

declare(strict_types=1);

namespace App\Shared\Domain\Clock;

use DateTimeImmutable;

/**
 * Источник времени. Инъекция вместо `new DateTimeImmutable()` в домене —
 * ради тестопригодности инвариантов, зависящих от времени (TTL токенов и т.п.).
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
