<?php

declare(strict_types=1);

namespace App\Access\Domain;

/**
 * Каталог прав — единственный источник истины (код), проецируется в БД сидером
 * при вводе таблиц access_* (docs/02-domain.md §5).
 */
enum Permission: string
{
    case UsersManage = 'users.manage';
    case AccessManage = 'access.manage';
    case CatalogManage = 'catalog.manage';
    case ElementsRead = 'elements.read';
    case ElementsWrite = 'elements.write';
}
