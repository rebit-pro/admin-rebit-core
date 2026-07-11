<?php

declare(strict_types=1);

namespace App\Auth\Application\Query\ListUsers;

use App\Auth\AuthRepository;

/**
 * Read-сторона (CQRS): постраничный список пользователей с поиском.
 */
final readonly class Fetcher
{
    public function __construct(private AuthRepository $users)
    {
    }

    /** @return array{items:list<array<string,mixed>>,total:int,page:int,perPage:int} */
    public function fetch(int $page, int $perPage, ?string $search): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        return [
            'items' => $this->users->listUsers($perPage, $offset, $search),
            'total' => $this->users->countUsers($search),
            'page' => $page,
            'perPage' => $perPage,
        ];
    }
}
