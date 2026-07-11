<?php

declare(strict_types=1);

namespace App\Auth\Presentation\Http\Action\Users;

use App\Auth\Application\Command\CreateUser\Command;
use App\Auth\Application\Command\CreateUser\Handler;
use App\Auth\Identity;
use App\Http\Response\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class CreateUserAction
{
    public function __construct(
        private Handler $handler,
        private JsonResponder $responder,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        /** @var Identity $actor */
        $actor = $request->getAttribute('identity');
        $body = (array)($request->getParsedBody() ?? []);

        $user = $this->handler->handle(new Command(
            $actor->role,
            self::str($body, 'email'),
            self::str($body, 'password'),
            self::str($body, 'name'),
            self::str($body, 'login'),
            self::str($body, 'role'),
            self::nullable($body, 'phone'),
            self::nullable($body, 'address'),
        ));

        return $this->responder->success($response, $user, 201);
    }

    /** @param array<string, mixed> $body */
    private static function str(array $body, string $key): string
    {
        return is_string($body[$key] ?? null) ? $body[$key] : '';
    }

    /** @param array<string, mixed> $body */
    private static function nullable(array $body, string $key): ?string
    {
        return is_string($body[$key] ?? null) ? $body[$key] : null;
    }
}
