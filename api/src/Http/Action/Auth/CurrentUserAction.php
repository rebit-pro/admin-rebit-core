<?php

declare(strict_types=1);

namespace App\Http\Action\Auth;

use App\Auth\AuthException;
use App\Auth\AuthService;
use App\Http\Response\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class CurrentUserAction
{
    public function __construct(
        private AuthService $authService,
        private JsonResponder $responder,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $identity = $this->authService->identity($request->getHeaderLine('Authorization'));

            return $this->responder->success($response, ['user' => $identity->toArray()]);
        } catch (AuthException $exception) {
            return $this->responder->error($response, $exception->getMessage(), $exception->status());
        }
    }
}
