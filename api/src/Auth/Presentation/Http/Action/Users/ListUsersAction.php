<?php

declare(strict_types=1);

namespace App\Auth\Presentation\Http\Action\Users;

use App\Auth\Application\Query\ListUsers\Fetcher;
use App\Http\Response\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListUsersAction
{
    public function __construct(
        private Fetcher $fetcher,
        private JsonResponder $responder,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $search = isset($query['search']) && is_string($query['search']) ? $query['search'] : null;

        $data = $this->fetcher->fetch(
            (int) ($query['page'] ?? 1),
            (int) ($query['perPage'] ?? 20),
            $search,
        );

        return $this->responder->success($response, $data);
    }
}
