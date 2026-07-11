<?php

declare(strict_types=1);

namespace App\Http\Action\Auth;

use App\Auth\AuthService;
use App\Http\Response\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class LogoutAction
{
    public function __construct(
        private AuthService $authService,
        private JsonResponder $responder,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->authService->logout($request->getHeaderLine('Authorization'));

        return $this->responder->success($response, null, 204);
    }
}
