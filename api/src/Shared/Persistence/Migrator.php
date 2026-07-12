<?php

declare(strict_types=1);

namespace App\Shared\Persistence;

/**
 * Применяет *.sql-миграции по возрастанию имени, отмечая версии в schema_migrations.
 * Каждый файл — в отдельной транзакции.
 */
final readonly class Migrator
{
    public function __construct(
        private \PDO $pdo,
        private string $migrationsPath,
    ) {}

    public function migrate(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(255) PRIMARY KEY,
                executed_at TIMESTAMPTZ NOT NULL DEFAULT now()
            )',
        );

        $files = glob($this->migrationsPath . '/*.sql') ?: [];
        sort($files);

        foreach ($files as $file) {
            $version = basename($file);

            if ($this->isExecuted($version)) {
                continue;
            }

            $sql = file_get_contents($file);

            if (false === $sql) {
                throw new \RuntimeException(sprintf('Cannot read migration %s.', $file));
            }

            $this->pdo->beginTransaction();

            try {
                $this->pdo->exec($sql);
                $statement = $this->pdo->prepare('INSERT INTO schema_migrations (version) VALUES (:version)');
                $statement->execute(['version' => $version]);
                $this->pdo->commit();
            } catch (\Throwable $exception) {
                $this->pdo->rollBack();

                throw $exception;
            }
        }
    }

    private function isExecuted(string $version): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = :version');
        $statement->execute(['version' => $version]);

        return false !== $statement->fetchColumn();
    }
}
