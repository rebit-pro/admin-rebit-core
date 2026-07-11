<?php

declare(strict_types=1);

namespace App\Http\Response;

use Psr\Http\Message\ResponseInterface;

final readonly class JsonResponder
{
    public function success(ResponseInterface $response, ?array $data = null, int $status = 200): ResponseInterface
    {
        if (204 === $status) {
            return $response->withStatus(204);
        }

        return $this->write($response, ['data' => $data], $status);
    }

    public function error(ResponseInterface $response, string $message, int $status = 400, array $errors = []): ResponseInterface
    {
        $payload = [
            'error' => [
                'message' => $message,
            ],
        ];

        if ([] !== $errors) {
            $payload['error']['errors'] = $errors;
        }

        return $this->write($response, $payload, $status);
    }

    /** @param array<string, mixed> $payload */
    private function write(ResponseInterface $response, array $payload, int $status): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status)
        ;
    }
}
