<?php

declare(strict_types=1);

namespace App\Auth\Presentation\Http\Action\Users;

use App\Auth\AuthRepository;
use App\Http\Response\JsonResponder;
use App\Shared\Http\Exception\HttpError;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class GetUserAction
{
    public function __construct(
        private AuthRepository $users,
        private JsonResponder $responder,
    ) {}

    /** @param array<string, string> $args */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->users->managedUserById((int)$args['id']);

        if (null === $user) {
            throw new HttpError('User not found.', 404);
        }

        return $this->responder->success($response, $user);
    }
}
