<?php

declare(strict_types=1);

namespace App\Shared\Console\Command;

use App\Shared\Persistence\Migrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'migrate', description: 'Применить миграции базы данных')]
final class MigrateCommand extends Command
{
    public function __construct(private readonly Migrator $migrator)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->migrator->migrate();

        (new SymfonyStyle($input, $output))->success('Миграции применены.');

        return Command::SUCCESS;
    }
}
