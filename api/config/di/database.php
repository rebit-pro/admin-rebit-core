<?php

declare(strict_types=1);

/**
 * Определение PDO. Целевая СУБД — PostgreSQL 17; ветка sqlite оставлена
 * только для локального fallback (упраздняется вместе с migrations/sqlite).
 */
return [
    PDO::class => static function (): PDO {
        $root = dirname(__DIR__, 2);
        $connection = $_ENV['DB_CONNECTION'] ?? 'pgsql';

        if ('sqlite' === $connection) {
            $database = $_ENV['DB_DATABASE'] ?? $root . '/var/app.sqlite';
            $database = str_starts_with($database, '/') ? $database : $root . '/' . $database;
            $directory = dirname($database);

            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            @chmod($directory, 0777);

            $pdo = new PDO('sqlite:' . $database, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            if (is_file($database)) {
                @chmod($database, 0666);
            }

            return $pdo;
        }

        $host = $_ENV['DB_HOST'] ?? 'db';
        $port = $_ENV['DB_PORT'] ?? '5432';
        $database = $_ENV['DB_DATABASE'] ?? 'rebit_admin';
        $username = $_ENV['DB_USERNAME'] ?? 'rebit';
        $password = $_ENV['DB_PASSWORD'] ?? 'rebit';

        return new PDO(
            sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $database),
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        );
    },
];
