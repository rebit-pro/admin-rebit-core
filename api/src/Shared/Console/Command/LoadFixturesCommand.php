<?php

declare(strict_types=1);

namespace App\Shared\Console\Command;

use App\Auth\Fixture\AuthFixture;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'fixtures', description: 'Загрузить тестовые данные', aliases: ['seed'])]
final class LoadFixturesCommand extends Command
{
    public function __construct(private readonly AuthFixture $fixture)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->fixture->load();

        (new SymfonyStyle($input, $output))->success('Фикстуры загружены.');

        return Command::SUCCESS;
    }
}
