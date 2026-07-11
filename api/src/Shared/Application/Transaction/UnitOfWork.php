<?php

declare(strict_types=1);

namespace App\Shared\Application\Transaction;

/**
 * Транзакционная граница. Handler оборачивает изменение агрегата + публикацию
 * событий в transactional(); InTransaction-подписчики выполняются внутри,
 * AfterCommit — после коммита (docs/02-domain.md §8).
 */
interface UnitOfWork
{
    /**
     * @template T
     * @param callable():T $work
     * @return T
     */
    public function transactional(callable $work): mixed;
}
