<?php

declare(strict_types=1);

namespace App\Shared\Application\Event;

use App\Shared\Domain\Event\DomainEvent;

interface EventSubscriber
{
    /** @return list<class-string<DomainEvent>> Классы событий, на которые подписан обработчик. */
    public function subscribedTo(): array;

    public function phase(): SubscriberPhase;

    public function handle(DomainEvent $event): void;
}
