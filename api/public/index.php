<?php

declare(strict_types=1);

use App\Shared\Http\Middleware\ErrorJsonMiddleware;
use App\Shared\Http\Middleware\SecurityHeadersMiddleware;
use Slim\Factory\AppFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

$container = require dirname(__DIR__) . '/config/container.php';
AppFactory::setContainer($container);

$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();

// Порядок (Slim LIFO): SecurityHeaders — внешний (навешивает заголовки и на ошибки),
// ErrorJson — ловит исключения роутинга/маршрутов и отдаёт единый JSON-контракт.
$app->add($container->get(ErrorJsonMiddleware::class));
$app->add($container->get(SecurityHeadersMiddleware::class));

(require dirname(__DIR__) . '/config/routes.php')($app);

$app->run();