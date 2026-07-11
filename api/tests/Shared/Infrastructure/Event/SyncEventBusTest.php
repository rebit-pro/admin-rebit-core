<?php

declare(strict_types=1);

namespace App\Test\Shared\Infrastructure\Event;

use App\Shared\Application\Event\EventSubscriber;
use App\Shared\Application\Event\SubscriberPhase;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Infrastructure\Event\SyncEventBus;
use ArrayObject;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SyncEventBusTest extends TestCase
{
    public function testInTransactionSubscriberIsHandledImmediatelyOnPublish(): void
    {
        $handled = new ArrayObject();
        $bus = new SyncEventBus([$this->subscriber(SubscriberPhase::InTransaction, $handled)]);

        $bus->publish($this->event());

        self::assertCount(1, $handled);
    }

    public function testAfterCommitSubscriberIsDeferredUntilFlush(): void
    {
        $handled = new ArrayObject();
        $bus = new SyncEventBus([$this->subscriber(SubscriberPhase::AfterCommit, $handled)]);

        $bus->publish($this->event());
        self::assertCount(0, $handled, 'after-commit не должен выполняться до коммита');

        $bus->flushAfterCommit();
        self::assertCount(1, $handled);
    }

    public function testDiscardPreventsAfterCommitDelivery(): void
    {
        $handled = new ArrayObject();
        $bus = new SyncEventBus([$this->subscriber(SubscriberPhase::AfterCommit, $handled)]);

        $bus->publish($this->event());
        $bus->discardAfterCommit();
        $bus->flushAfterCommit();

        self::assertCount(0, $handled);
    }

    public function testFlushIsIdempotentAndDrainsQueue(): void
    {
        $handled = new ArrayObject();
        $bus = new SyncEventBus([$this->subscriber(SubscriberPhase::AfterCommit, $handled)]);

        $bus->publish($this->event());
        $bus->flushAfterCommit();
        $bus->flushAfterCommit();

        self::assertCount(1, $handled, 'повторный flush не должен доставлять повторно');
    }

    private function subscriber(SubscriberPhase $phase, ArrayObject $sink): EventSubscriber
    {
        return new class($phase, $sink) implements EventSubscriber {
            public function __construct(private SubscriberPhase $phase, private ArrayObject $sink)
            {
            }

            public function subscribedTo(): array
            {
                return [DomainEvent::class];
            }

            public function phase(): SubscriberPhase
            {
                return $this->phase;
            }

            public function handle(DomainEvent $event): void
            {
                $this->sink->append($event);
            }
        };
    }

    private function event(): DomainEvent
    {
        return new class implements DomainEvent {
            public function eventName(): string
            {
                return 'test.event';
            }

            public function occurredAt(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-01-01T00:00:00+00:00');
            }

            public function aggregateId(): string
            {
                return 'agg-1';
            }

            public function payload(): array
            {
                return [];
            }
        };
    }
}
