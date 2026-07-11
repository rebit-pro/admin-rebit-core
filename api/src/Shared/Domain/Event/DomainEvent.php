<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

/**
 * Доменное событие — факт свершившегося изменения агрегата.
 * Контракт см. docs/02-domain.md §8.
 */
interface DomainEvent
{
    public function eventName(): string;

    public function occurredAt(): \DateTimeImmutable;

    public function aggregateId(): string;

    /** @return array<string, mixed> Сериализуемая полезная нагрузка (с redaction секретов/ПДн). */
    public function payload(): array;
}
