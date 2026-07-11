<?php

declare(strict_types=1);

/**
 * Определение PDO. СУБД — PostgreSQL 17 (единственная: sqlite-ветка упразднена вместе с migrations/sqlite).
 */
return [
    PDO::class => static function(): PDO {
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
