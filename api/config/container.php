<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Dotenv\Dotenv;

$root = dirname(__DIR__);

if (is_file($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

$builder = new ContainerBuilder();
$builder->useAutowiring(true);

foreach (glob(__DIR__ . '/di/*.php') as $definitions) {
    $builder->addDefinitions($definitions);
}

return $builder->build();
