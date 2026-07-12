<?php

declare(strict_types=1);

namespace App\Shared\Config;

/**
 * Разворачивает контракт docker secrets: для каждой переменной `<NAME>_FILE`
 * читает файл и кладёт содержимое в `<NAME>`. `_FILE` имеет приоритет над
 * уже установленным `<NAME>` (секрет из Swarm перекрывает значение из .env).
 */
final class EnvFileResolver
{
    /** @param array<string, mixed> $env */
    public static function resolve(array $env): void
    {
        foreach ($env as $name => $path) {
            if (!is_string($name) || !str_ends_with($name, '_FILE') || !is_string($path) || '' === $path) {
                continue;
            }

            $target = substr($name, 0, -strlen('_FILE'));

            if ('' === $target) {
                continue;
            }

            if (!is_file($path) || !is_readable($path)) {
                throw new \RuntimeException(sprintf('Env %s points to unreadable file %s.', $name, $path));
            }

            $value = rtrim((string)file_get_contents($path), "\r\n");

            $_ENV[$target] = $value;
            $_SERVER[$target] = $value;
        }
    }
}
