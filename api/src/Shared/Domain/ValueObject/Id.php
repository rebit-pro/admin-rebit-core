<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

/**
 * Идентификатор доменного агрегата.
 *
 * Генерируется приложением как UUID v7 (сортируемый по времени) — PostgreSQL 17
 * не умеет `uuidv7()` нативно, поэтому DEFAULT у uuid-колонок отсутствует
 * (см. docs/03-database.md §12).
 */
final readonly class Id implements \Stringable
{
    private string $value;

    public function __construct(string $value)
    {
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException(sprintf('Invalid UUID: "%s".', $value));
        }

        $this->value = strtolower($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function generate(): self
    {
        return new self((string)Uuid::v7());
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
