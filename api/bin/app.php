#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Auth\Console\Command\PurgeExpiredTokensCommand;
use App\Shared\Console\Command\LoadFixturesCommand;
use App\Shared\Console\Command\MigrateCommand;
use DI\Container;
use Symfony\Component\Console\Application;

require dirname(__DIR__) . '/vendor/autoload.php';

$container = require dirname(__DIR__) . '/config/container.php';

if (!$container instanceof Container) {
    fwrite(STDERR, "Container boot failed.\n");

    exit(1);
}

$application = new Application('ReBit Admin Core');
$application->add($container->get(MigrateCommand::class));
$application->add($container->get(LoadFixturesCommand::class));
$application->add($container->get(PurgeExpiredTokensCommand::class));
$application->run();
