<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Event;

use App\Shared\Application\Event\EventPublisher;
use App\Shared\Application\Event\EventSubscriber;
use App\Shared\Application\Event\SubscriberPhase;
use App\Shared\Domain\Event\DomainEvent;
use Psr\Log\LoggerInterface;

/**
 * Синхронная in-process шина событий.
 *
 * InTransaction-подписчики выполняются немедленно при publish() (внутри той же
 * транзакции, что открыл UnitOfWork). AfterCommit-подписчики буферизуются и
 * выполняются через flushAfterCommit() уже ПОСЛЕ коммита; их ошибки логируются,
 * но не откатывают операцию (docs/02-domain.md §8).
 */
final class SyncEventBus implements EventPublisher
{
    /** @var list<EventSubscriber> */
    private array $subscribers;

    /** @var list<DomainEvent> */
    private array $afterCommitQueue = [];

    /** @param iterable<EventSubscriber> $subscribers */
    public function __construct(
        iterable $subscribers = [],
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->subscribers = is_array($subscribers)
            ? array_values($subscribers)
            : iterator_to_array($subscribers, false);
    }

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            foreach ($this->matching($event, SubscriberPhase::InTransaction) as $subscriber) {
                $subscriber->handle($event);
            }

            $this->afterCommitQueue[] = $event;
        }
    }

    /** Вызывается UnitOfWork после успешного коммита. */
    public function flushAfterCommit(): void
    {
        $events = $this->afterCommitQueue;
        $this->afterCommitQueue = [];

        foreach ($events as $event) {
            foreach ($this->matching($event, SubscriberPhase::AfterCommit) as $subscriber) {
                try {
                    $subscriber->handle($event);
                } catch (\Throwable $exception) {
                    $this->logger?->error('After-commit subscriber failed', [
                        'event' => $event->eventName(),
                        'subscriber' => $subscriber::class,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            }
        }
    }

    /** Вызывается UnitOfWork при откате: отменяет отложенные after-commit эффекты. */
    public function discardAfterCommit(): void
    {
        $this->afterCommitQueue = [];
    }

    /** @return list<EventSubscriber> */
    private function matching(DomainEvent $event, SubscriberPhase $phase): array
    {
        $result = [];

        foreach ($this->subscribers as $subscriber) {
            if ($subscriber->phase() !== $phase) {
                continue;
            }

            foreach ($subscriber->subscribedTo() as $type) {
                if ($event instanceof $type) {
                    $result[] = $subscriber;

                    break;
                }
            }
        }

        return $result;
    }
}
