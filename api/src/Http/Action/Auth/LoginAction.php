<?php

declare(strict_types=1);

namespace App\Http\Action\Auth;

use App\Auth\AuthException;
use App\Auth\AuthService;
use App\Http\Response\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class LoginAction
{
    public function __construct(
        private AuthService $authService,
        private JsonResponder $responder,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = $request->getParsedBody();
            $serverParams = $request->getServerParams();
            $ip = is_string($serverParams['REMOTE_ADDR'] ?? null) ? $serverParams['REMOTE_ADDR'] : null;
            $data = $this->authService->login(is_array($payload) ? $payload : [], $ip);

            return $this->responder->success($response, $data, 200);
        } catch (AuthException $exception) {
            return $this->responder->error($response, $exception->getMessage(), $exception->status());
        }
    }
}
