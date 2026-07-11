<?php

declare(strict_types=1);

namespace App\Http\Action;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Readiness: по нему Swarm-healthcheck решает про rolling update / auto-rollback,
 * поэтому недоступность БД обязана давать 503 (docs/04-devops.md §9).
 * PDO берётся из контейнера лениво: ошибка коннекта — штатная ветка проверки,
 * а не падение DI при инжекте в конструктор.
 */
final readonly class HealthAction
{
    public function __construct(private ContainerInterface $container) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $databaseOk = true;

        try {
            $this->container->get(\PDO::class)->query('SELECT 1');
        } catch (\Throwable) {
            $databaseOk = false;
        }

        $payload = json_encode([
            'status' => $databaseOk ? 'ok' : 'fail',
            'service' => 'rebit-admin-core',
            'database' => $databaseOk ? 'ok' : 'unavailable',
        ], JSON_THROW_ON_ERROR);

        $response->getBody()->write($payload);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($databaseOk ? 200 : 503)
        ;
    }
}
