<?php

declare(strict_types=1);

namespace App\Access\Presentation\Http\Middleware;

use App\Access\Application\AccessDecision;
use App\Access\Domain\Permission;
use App\Auth\Identity;
use App\Shared\Http\Exception\HttpError;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Требует у аутентифицированного пользователя конкретное право (RBAC).
 * Ставится после AuthenticationMiddleware (читает атрибут 'identity').
 */
final readonly class RequirePermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AccessDecision $access,
        private Permission $permission,
    ) {}

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $identity = $request->getAttribute('identity');

        if (!$identity instanceof Identity) {
            throw new HttpError('Authorization is required.', 401);
        }

        if (!$this->access->isGranted($identity->role, $this->permission)) {
            throw new HttpError('You are not allowed to perform this action.', 403);
        }

        return $handler->handle($request);
    }
}
