<?php

declare(strict_types=1);

namespace App\Http\Action\Auth;

use App\Auth\AuthException;
use App\Auth\AuthService;
use App\Http\Response\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class RequestRegistrationCodeAction
{
    public function __construct(
        private AuthService $authService,
        private JsonResponder $responder,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = $request->getParsedBody();
            $data = $this->authService->requestRegistrationCode(is_array($payload) ? $payload : []);

            return $this->responder->success($response, $data, 200);
        } catch (AuthException $exception) {
            return $this->responder->error($response, $exception->getMessage(), $exception->status());
        }
    }
}
