<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Application\Transaction\UnitOfWork;
use App\Shared\Infrastructure\Event\SyncEventBus;
use PDO;
use Throwable;

final readonly class PdoUnitOfWork implements UnitOfWork
{
    public function __construct(
        private PDO $pdo,
        private SyncEventBus $bus,
    ) {
    }

    public function transactional(callable $work): mixed
    {
        // Вложенный вызов присоединяется к внешней транзакции (PDO не умеет вложенные begin).
        if ($this->pdo->inTransaction()) {
            return $work();
        }

        $this->pdo->beginTransaction();

        try {
            $result = $work();
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->bus->discardAfterCommit();

            throw $exception;
        }

        $this->bus->flushAfterCommit();

        return $result;
    }
}
