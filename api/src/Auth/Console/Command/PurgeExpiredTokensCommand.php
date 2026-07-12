<?php

declare(strict_types=1);

namespace App\Auth\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Cron-задача (swarm-cronjob, ежечасно): чистит истёкшие access-токены
 * и протухшие регистрационные коды (docs/04-devops.md §7).
 */
#[AsCommand(name: 'auth:purge-expired-tokens', description: 'Удалить истёкшие access-токены и регистрационные коды')]
final class PurgeExpiredTokensCommand extends Command
{
    public function __construct(private readonly \PDO $pdo)
    {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tokens = $this->pdo
            ->query('DELETE FROM auth_access_tokens WHERE expires_at <= CURRENT_TIMESTAMP')
            ->rowCount()
        ;
        $codes = $this->pdo
            ->query('DELETE FROM auth_registration_codes WHERE code_expires_at <= CURRENT_TIMESTAMP')
            ->rowCount()
        ;

        (new SymfonyStyle($input, $output))->success(sprintf(
            'Удалено: %d токенов, %d регистрационных кодов.',
            $tokens,
            $codes,
        ));

        return Command::SUCCESS;
    }
}
