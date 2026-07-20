<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ChroniclePublisher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:chronicle:publish-scheduled',
    description: 'Публикует запланированные записи хроники',
)]
final class PublishScheduledChronicleCommand extends Command
{
    public function __construct(
        private readonly ChroniclePublisher $publisher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = $this->publisher->publishScheduled();
        $io->success(sprintf('Опубликовано записей: %d', $count));

        return Command::SUCCESS;
    }
}
