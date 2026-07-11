<?php

declare(strict_types=1);

namespace App\Auth\Presentation\Http\Middleware;

use App\Auth\AuthService;
use App\Shared\Application\ActorContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Аутентификация по Bearer-токену. Кладёт App\Auth\Identity в атрибут запроса
 * 'identity'. Невалидный/отсутствующий токен → AuthException(401) →
 * ErrorJsonMiddleware (docs/01-scenarios.md §6.3).
 */
final readonly class AuthenticationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthService $auth,
        private ActorContext $actor,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $identity = $this->auth->identity($request->getHeaderLine('Authorization'));

        $server = $request->getServerParams();
        $userAgent = $request->getHeaderLine('User-Agent');
        $this->actor->set(
            (string) $identity->id,
            isset($server['REMOTE_ADDR']) && is_string($server['REMOTE_ADDR']) ? $server['REMOTE_ADDR'] : null,
            '' !== $userAgent ? $userAgent : null,
        );

        return $handler->handle($request->withAttribute('identity', $identity));
    }
}
