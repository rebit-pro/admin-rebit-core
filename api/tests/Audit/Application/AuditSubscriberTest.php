<?php

declare(strict_types=1);

namespace App\Test\Audit\Application;

use App\Audit\Application\AuditSubscriber;
use App\Audit\Application\Port\AuditLog;
use App\Auth\Domain\Event\UserCreated;
use App\Shared\Application\ActorContext;
use App\Shared\Application\Event\SubscriberPhase;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class AuditSubscriberTest extends TestCase
{
    public function testRunsInTransaction(): void
    {
        $subscriber = new AuditSubscriber($this->auditLog($captured), new ActorContext());

        self::assertSame(SubscriberPhase::InTransaction, $subscriber->phase());
    }

    public function testWritesEventWithActorAndRedactedPayload(): void
    {
        $actor = new ActorContext();
        $actor->set('5', '127.0.0.1', 'UA/1.0');

        $subscriber = new AuditSubscriber($this->auditLog($captured), $actor);
        $subscriber->handle(new UserCreated(42, 'admin', new \DateTimeImmutable('2026-01-01T00:00:00+00:00')));

        self::assertSame(5, $captured['actorId']);
        self::assertSame('auth.user.created', $captured['action']);
        self::assertSame('user', $captured['subjectType']);
        self::assertSame('42', $captured['subjectId']);
        self::assertSame(['userId' => 42, 'role' => 'admin'], $captured['changes']);
        self::assertSame('127.0.0.1', $captured['ip']);
        self::assertArrayNotHasKey('password_hash', $captured['changes']);
    }

    /** @param-out array<string, mixed> $captured */
    private function auditLog(?array &$captured): AuditLog
    {
        $captured = [];

        return new class($captured) implements AuditLog {
            /** @var array<string, mixed> */
            private array $ref;

            /** @param array<string, mixed> $ref */
            public function __construct(array &$ref)
            {
                $this->ref = &$ref;
            }

            public function append(
                ?int $actorId,
                string $action,
                string $subjectType,
                string $subjectId,
                array $changes,
                ?string $ip,
                ?string $userAgent,
            ): void {
                $this->ref = compact('actorId', 'action', 'subjectType', 'subjectId', 'changes', 'ip', 'userAgent');
            }
        };
    }
}
