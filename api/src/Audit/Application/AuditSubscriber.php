<?php

declare(strict_types=1);

namespace App\Audit\Application;

use App\Audit\Application\Port\AuditLog;
use App\Auth\Domain\Event\UserBlocked;
use App\Auth\Domain\Event\UserCreated;
use App\Auth\Domain\Event\UserDeleted;
use App\Auth\Domain\Event\UserEmailChanged;
use App\Auth\Domain\Event\UserLoginChanged;
use App\Auth\Domain\Event\UserPasswordChanged;
use App\Auth\Domain\Event\UserRoleChanged;
use App\Shared\Application\ActorContext;
use App\Shared\Application\Event\EventSubscriber;
use App\Shared\Application\Event\SubscriberPhase;
use App\Shared\Domain\Event\DomainEvent;

/**
 * Пишет значимые доменные события в audit_log. Работает in-transaction
 * (атомарно с бизнес-операцией). Actor/IP/UA берёт из ActorContext.
 * Нагрузка события уже без секретов (redaction — в самих событиях).
 */
final readonly class AuditSubscriber implements EventSubscriber
{
    public function __construct(
        private AuditLog $audit,
        private ActorContext $actor,
    ) {}

    public function subscribedTo(): array
    {
        return [
            UserCreated::class,
            UserRoleChanged::class,
            UserBlocked::class,
            UserDeleted::class,
            UserPasswordChanged::class,
            UserLoginChanged::class,
            UserEmailChanged::class,
        ];
    }

    public function phase(): SubscriberPhase
    {
        return SubscriberPhase::InTransaction;
    }

    public function handle(DomainEvent $event): void
    {
        $actorId = $this->actor->actorId();

        $this->audit->append(
            null === $actorId ? null : (int)$actorId,
            $event->eventName(),
            $this->subjectType($event->eventName()),
            $event->aggregateId(),
            $event->payload(),
            $this->actor->ip(),
            $this->actor->userAgent(),
        );
    }

    private function subjectType(string $eventName): string
    {
        // 'auth.user.created' → 'user'
        return explode('.', $eventName)[1] ?? 'unknown';
    }
}
