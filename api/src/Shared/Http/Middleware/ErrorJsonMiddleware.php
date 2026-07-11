<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Http\HttpException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException as SlimHttpException;
use Throwable;

/**
 * Единый JSON-контракт ошибок: {"error":{"message":..., "errors":{...}}}.
 * ValidationException → 422, доменные HttpException → их статус,
 * Slim HTTP-исключения (404/405) → их код, прочее → 500.
 */
final readonly class ErrorJsonMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private LoggerInterface $logger,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (ValidationException $exception) {
            return $this->json(422, $exception->getMessage(), $exception->errors());
        } catch (HttpException $exception) {
            return $this->json($exception->status(), $exception->getMessage());
        } catch (SlimHttpException $exception) {
            $status = $exception->getCode();

            return $this->json($status >= 400 && $status < 600 ? $status : 500, $exception->getMessage());
        } catch (Throwable $exception) {
            $this->logger->error('Unhandled exception', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile() . ':' . $exception->getLine(),
            ]);

            $debug = (bool) ($_ENV['APP_DEBUG'] ?? false);

            return $this->json(500, $debug ? $exception->getMessage() : 'Internal Server Error');
        }
    }

    /** @param array<string, list<string>> $errors */
    private function json(int $status, string $message, array $errors = []): ResponseInterface
    {
        $error = ['message' => $message];

        if ([] !== $errors) {
            $error['errors'] = $errors;
        }

        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write(json_encode(['error' => $error], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
