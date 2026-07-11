<?php

declare(strict_types=1);

use App\Audit\Application\Port\AuditLog;
use App\Audit\Infrastructure\Persistence\PdoAuditLog;

use function DI\get;

return [
    AuditLog::class => get(PdoAuditLog::class),
];
