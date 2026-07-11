<?php

declare(strict_types=1);

namespace App\Shared\Application\Event;

use App\Shared\Domain\Event\DomainEvent;

/**
 * Порт публикации доменных событий. Реализация в MVP — синхронная шина
 * (SyncEventBus); замена на outbox/брокер не затрагивает доменный код.
 */
interface EventPublisher
{
    public function publish(DomainEvent ...$events): void;
}
