<?php

declare(strict_types=1);

namespace App\Auth\Presentation\Http\Action\Account;

use App\Auth\Application\Command\ChangePassword\Command;
use App\Auth\Application\Command\ChangePassword\Handler;
use App\Auth\Identity;
use App\Http\Response\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ChangePasswordAction
{
    public function __construct(
        private Handler $handler,
        private JsonResponder $responder,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        /** @var Identity $identity */
        $identity = $request->getAttribute('identity');
        $body = (array) ($request->getParsedBody() ?? []);

        $result = $this->handler->handle(new Command(
            $identity->id,
            self::str($body, 'currentPassword'),
            self::str($body, 'newPassword'),
            self::str($body, 'newPasswordConfirmation'),
        ));

        return $this->responder->success($response, $result);
    }

    /** @param array<string, mixed> $body */
    private static function str(array $body, string $key): string
    {
        return is_string($body[$key] ?? null) ? $body[$key] : '';
    }
}
