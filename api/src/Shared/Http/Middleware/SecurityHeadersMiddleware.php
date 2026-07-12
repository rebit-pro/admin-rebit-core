<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Базовые security-заголовки на все ответы (docs/01-scenarios.md §6.5).
 * HSTS/CSP навешивает шлюз Traefik/nginx на проде; здесь — прикладной минимум.
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        return $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('X-Permitted-Cross-Domain-Policies', 'none')
        ;
    }
}
