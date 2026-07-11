<?php

declare(strict_types=1);

namespace App\Shared\Application;

/**
 * Request-scoped контекст действующего лица (кто выполняет запрос).
 * Заполняется AuthenticationMiddleware, читается подписчиками (напр. Audit).
 * Контейнер строится на каждый запрос, поэтому синглтон безопасен.
 */
final class ActorContext
{
    private ?string $actorId = null;
    private ?string $ip = null;
    private ?string $userAgent = null;

    public function set(?string $actorId, ?string $ip, ?string $userAgent): void
    {
        $this->actorId = $actorId;
        $this->ip = $ip;
        $this->userAgent = $userAgent;
    }

    public function actorId(): ?string
    {
        return $this->actorId;
    }

    public function ip(): ?string
    {
        return $this->ip;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
    }
}
