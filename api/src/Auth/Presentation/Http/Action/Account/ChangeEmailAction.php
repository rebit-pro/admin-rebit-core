<?php

declare(strict_types=1);

namespace App\Auth\Presentation\Http\Action\Account;

use App\Auth\Application\Command\ChangeEmail\Command;
use App\Auth\Application\Command\ChangeEmail\Handler;
use App\Auth\Identity;
use App\Http\Response\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ChangeEmailAction
{
    public function __construct(
        private Handler $handler,
        private JsonResponder $responder,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        /** @var Identity $identity */
        $identity = $request->getAttribute('identity');
        $body = (array)($request->getParsedBody() ?? []);

        $result = $this->handler->handle(new Command(
            $identity->id,
            is_string($body['newEmail'] ?? null) ? $body['newEmail'] : '',
            is_string($body['currentPassword'] ?? null) ? $body['currentPassword'] : '',
        ));

        return $this->responder->success($response, $result);
    }
}
