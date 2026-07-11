<?php

declare(strict_types=1);

namespace App\Shared\Application\Event;

/**
 * Фаза выполнения подписчика относительно коммита транзакции (docs/02-domain.md §8):
 *  - InTransaction — атомарно с бизнес-операцией (аудит): падение откатывает операцию;
 *  - AfterCommit  — строго после коммита (инвалидация кэша, интеграции): не откатывает.
 */
enum SubscriberPhase
{
    case InTransaction;
    case AfterCommit;
}
