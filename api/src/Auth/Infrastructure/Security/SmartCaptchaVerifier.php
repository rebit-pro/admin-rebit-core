<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Security;

use App\Auth\Application\Port\CaptchaVerifier;
use Psr\Log\LoggerInterface;

/**
 * Серверная проверка Yandex SmartCaptcha. Fail-closed: недоступность сервиса
 * проверки не пропускает вход без капчи (админка, docs/04-devops.md §16.3).
 */
final readonly class SmartCaptchaVerifier implements CaptchaVerifier
{
    private const VALIDATE_URL = 'https://smartcaptcha.yandexcloud.net/validate';
    private const TIMEOUT_SECONDS = 5;

    public function __construct(
        private string $serverKey,
        private LoggerInterface $logger,
    ) {}

    #[\Override]
    public function verify(?string $token, ?string $ip = null): bool
    {
        if (null === $token || '' === trim($token)) {
            return false;
        }

        $fields = ['secret' => $this->serverKey, 'token' => $token];

        if (null !== $ip && '' !== $ip) {
            $fields['ip'] = $ip;
        }

        $handle = curl_init(self::VALIDATE_URL);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
        ]);

        $body = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($handle);
        curl_close($handle);

        if (false === $body || 200 !== $status) {
            $this->logger->error('SmartCaptcha validate request failed.', [
                'http_status' => $status,
                'curl_error' => $curlError,
            ]);

            return false;
        }

        try {
            /** @var array{status?: string} $response */
            $response = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->logger->error('SmartCaptcha validate response is not JSON.', ['error' => $exception->getMessage()]);

            return false;
        }

        return 'ok' === ($response['status'] ?? null);
    }
}
