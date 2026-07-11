<?php

declare(strict_types=1);

namespace App\Auth;

use DateTimeImmutable;

final readonly class AuthService
{
    private const TOKEN_TTL = '+24 hours';
    private const REGISTRATION_CODE = '123456';

    public function __construct(
        private AuthRepository $repository,
        private TokenFactory $tokenFactory,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public function login(array $payload): array
    {
        $identifier = $this->requiredString($payload['email'] ?? $payload['login'] ?? null, 'Email or login is required.');
        $password = $this->requiredPassword($payload['password'] ?? null);

        $user = $this->repository->findUserByEmailOrLogin($identifier);

        if (null === $user || !password_verify($password, $user['password_hash'])) {
            throw new AuthException('Invalid email, login, or password.', 401);
        }

        if ('blocked' === ($user['status'] ?? 'active')) {
            throw new AuthException('Account is blocked.', 403);
        }

        return $this->issueToken($user);
    }

    /** @param array<string, mixed> $payload */
    public function requestRegistrationCode(array $payload): array
    {
        $email = $this->requiredEmail($payload['email'] ?? null);
        $password = $this->requiredPassword($payload['password'] ?? null);
        $name = $this->nameFromEmail($email);
        $codeExpiresAt = new DateTimeImmutable('+15 minutes');
        $resendAvailableAt = new DateTimeImmutable('+1 minute');

        $this->repository->storeRegistrationCode(
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $name,
            self::REGISTRATION_CODE,
            $codeExpiresAt,
            $resendAvailableAt,
        );

        return [
            'email' => $email,
            'codeExpiresAt' => $codeExpiresAt->format(DATE_ATOM),
            'resendAvailableAt' => $resendAvailableAt->format(DATE_ATOM),
        ];
    }

    /** @param array<string, mixed> $payload */
    public function confirmRegistration(array $payload): array
    {
        $email = $this->requiredEmail($payload['email'] ?? null);
        $code = $this->requiredString($payload['code'] ?? null, 'Confirmation code is required.');

        return $this->repository->transaction(function () use ($email, $code): array {
            $registration = $this->repository->findRegistrationCode($email);

            if (null === $registration) {
                throw new AuthException('Confirmation code was not requested.', 422);
            }

            if ($registration['code'] !== $code) {
                throw new AuthException('Invalid confirmation code.', 422);
            }

            if (new DateTimeImmutable($registration['code_expires_at']) <= new DateTimeImmutable()) {
                throw new AuthException('Confirmation code has expired.', 422);
            }

            $user = $this->repository->upsertUser(
                $registration['email'],
                $registration['password_hash'],
                $registration['name'],
                $this->loginFromEmail($registration['email']),
            );
            $this->repository->deleteRegistrationCode($email);

            return $this->issueToken($user);
        });
    }

    public function logout(?string $authorizationHeader): void
    {
        $token = $this->bearerToken($authorizationHeader);

        if (null === $token) {
            return;
        }

        $this->repository->deleteAccessToken($this->tokenFactory->hash($token));
    }

    public function identity(?string $authorizationHeader): Identity
    {
        $token = $this->bearerToken($authorizationHeader);

        if (null === $token) {
            throw new AuthException('Authorization is required.', 401);
        }

        $user = $this->repository->findUserByTokenHash($this->tokenFactory->hash($token));

        if (null === $user) {
            throw new AuthException('Session is invalid or expired.', 401);
        }

        return new Identity(
            $user['id'],
            $user['email'],
            $user['name'],
            $user['role'],
            $user['login'],
            $user['phone'],
            $user['address'],
        );
    }

    /** @param array{id:int,email:string,password_hash:string,name:string,role:string,login:string,phone:?string,address:?string} $user */
    private function issueToken(array $user): array
    {
        $token = $this->tokenFactory->create();
        $expiresAt = new DateTimeImmutable(self::TOKEN_TTL);
        $this->repository->storeAccessToken($this->tokenFactory->hash($token), $user['id'], $expiresAt);

        return [
            'token' => $token,
            'expiresAt' => $expiresAt->format(DATE_ATOM),
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'login' => $user['login'],
                'phone' => $user['phone'],
                'address' => $user['address'],
            ],
        ];
    }

    private function bearerToken(?string $authorizationHeader): ?string
    {
        if (null === $authorizationHeader || '' === trim($authorizationHeader)) {
            return null;
        }

        if (1 !== preg_match('/^Bearer\s+(?<token>\S+)$/i', $authorizationHeader, $matches)) {
            return null;
        }

        return $matches['token'];
    }

    private function requiredEmail(mixed $value): string
    {
        $email = $this->requiredString($value, 'Email is required.');

        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new AuthException('Email is invalid.', 422);
        }

        return mb_strtolower($email);
    }

    private function requiredPassword(mixed $value): string
    {
        $password = $this->requiredString($value, 'Password is required.');

        if (6 > mb_strlen($password)) {
            throw new AuthException('Password must contain at least 6 characters.', 422);
        }

        return $password;
    }

    private function requiredString(mixed $value, string $message): string
    {
        if (!is_string($value) || '' === trim($value)) {
            throw new AuthException($message, 422);
        }

        return trim($value);
    }

    private function nameFromEmail(string $email): string
    {
        $name = $this->loginFromEmail($email);

        return '' === $name ? $email : $name;
    }

    private function loginFromEmail(string $email): string
    {
        return explode('@', $email)[0] ?? $email;
    }
}
