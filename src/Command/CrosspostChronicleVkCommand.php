<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ChronicleVkCrossposter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:chronicle:crosspost-vk',
    description: 'Публикует due-записи хроники на стену VK (идемпотентно)',
)]
final class CrosspostChronicleVkCommand extends Command
{
    public function __construct(
        private readonly ChronicleVkCrossposter $crossposter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Макс. записей за запуск', '20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limit = max(1, (int) $input->getOption('limit'));
        $stats = $this->crossposter->crosspostDue($limit);
        $io->success(sprintf(
            'VK crosspost: posted=%d deferred=%d skipped=%d failed=%d',
            $stats['posted'],
            $stats['deferred'],
            $stats['skipped'],
            $stats['failed'],
        ));

        return $stats['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
