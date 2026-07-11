<?php

declare(strict_types=1);

use App\Audit\Application\AuditSubscriber;
use App\Shared\Application\Event\EventPublisher;
use App\Shared\Application\Transaction\UnitOfWork;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Infrastructure\Clock\SystemClock;
use App\Shared\Infrastructure\Event\SyncEventBus;
use App\Shared\Infrastructure\Persistence\PdoUnitOfWork;
use App\Shared\Persistence\Migrator;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;

use function DI\create;
use function DI\get;

/**
 * Ядро Shared: логгер (stderr JSON), фабрика ответов, синхронная шина событий,
 * UnitOfWork, часы, мигратор. Подписчиков в шину добавляем при вводе Audit (#12).
 */
return [
    LoggerInterface::class => static function(): LoggerInterface {
        $logger = new Logger('app');
        $handler = new StreamHandler('php://stderr', Level::Debug);
        $handler->setFormatter(new JsonFormatter());
        $logger->pushHandler($handler);

        return $logger;
    },

    ResponseFactoryInterface::class => create(ResponseFactory::class),

    Clock::class => get(SystemClock::class),

    SyncEventBus::class => static fn(ContainerInterface $c): SyncEventBus => new SyncEventBus(
        [$c->get(AuditSubscriber::class)],
        $c->get(LoggerInterface::class),
    ),
    EventPublisher::class => get(SyncEventBus::class),

    UnitOfWork::class => get(PdoUnitOfWork::class),

    Migrator::class => static fn(ContainerInterface $c): Migrator => new Migrator(
        $c->get(PDO::class),
        dirname(__DIR__, 2) . '/migrations',
    ),
];
