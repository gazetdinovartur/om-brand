<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ChronicleVkWallSync;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:chronicle:sync-vk-wall',
    description: 'Тянет новые посты со стены VK в хронику',
)]
final class SyncChronicleVkWallCommand extends Command
{
    public function __construct(
        private readonly ChronicleVkWallSync $sync,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('count', null, InputOption::VALUE_REQUIRED, 'Сколько последних постов смотреть', '20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $stats = $this->sync->sync(max(1, (int) $input->getOption('count')));
        $io->success(sprintf('VK wall sync: created=%d skipped=%d', $stats['created'], $stats['skipped']));

        return Command::SUCCESS;
    }
}
