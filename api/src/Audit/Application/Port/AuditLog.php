<?php

declare(strict_types=1);

namespace App\Audit\Application\Port;

interface AuditLog
{
    /**
     * @param array<string, mixed> $changes уже отредактированная нагрузка (без секретов/ПДн)
     */
    public function append(
        ?int $actorId,
        string $action,
        string $subjectType,
        string $subjectId,
        array $changes,
        ?string $ip,
        ?string $userAgent,
    ): void;
}
