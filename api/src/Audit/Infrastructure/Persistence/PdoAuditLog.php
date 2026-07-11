<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Persistence;

use App\Audit\Application\Port\AuditLog;
use PDO;

final readonly class PdoAuditLog implements AuditLog
{
    public function __construct(private PDO $pdo)
    {
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
        $statement = $this->pdo->prepare(
            'INSERT INTO audit_log (actor_id, action, subject_type, subject_id, changes, ip, user_agent)
             VALUES (:actor_id, :action, :subject_type, :subject_id, CAST(:changes AS JSONB), CAST(:ip AS INET), :user_agent)'
        );
        $statement->execute([
            'actor_id' => $actorId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'changes' => json_encode($changes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'ip' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
