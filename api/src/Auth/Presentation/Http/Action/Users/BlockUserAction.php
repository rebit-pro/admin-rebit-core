<?php

declare(strict_types=1);

namespace App\Auth\Presentation\Http\Action\Users;

use App\Auth\Application\Command\SetUserStatus\Command;
use App\Auth\Application\Command\SetUserStatus\Handler;
use App\Auth\Identity;
use App\Http\Response\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class BlockUserAction
{
    public function __construct(
        private Handler $handler,
        private JsonResponder $responder,
    ) {
    }

    /** @param array<string, string> $args */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        /** @var Identity $actor */
        $actor = $request->getAttribute('identity');

        $user = $this->handler->handle(new Command($actor->role, $actor->id, (int) $args['id'], 'blocked'));

        return $this->responder->success($response, $user);
    }
}
