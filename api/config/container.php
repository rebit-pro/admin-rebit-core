<?php

declare(strict_types=1);

use App\Shared\Config\EnvFileResolver;
use DI\ContainerBuilder;
use Dotenv\Dotenv;

$root = dirname(__DIR__);

if (is_file($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

// Docker secrets: <NAME>_FILE → <NAME> (приоритет над .env), см. docs/04-devops.md §8.
EnvFileResolver::resolve($_ENV);

$builder = new ContainerBuilder();
$builder->useAutowiring(true);

foreach (glob(__DIR__ . '/di/*.php') as $definitions) {
    $builder->addDefinitions($definitions);
}

return $builder->build();
