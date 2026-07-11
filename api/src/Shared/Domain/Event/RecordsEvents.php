<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

/**
 * Примесь для агрегатов: накапливает доменные события, которые Handler
 * забирает через pullDomainEvents() и публикует после сохранения.
 */
trait RecordsEvents
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    /** @return list<DomainEvent> */
    public function pullDomainEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    protected function recordThat(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }
}
