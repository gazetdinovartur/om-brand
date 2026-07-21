<?php

namespace App\Command;

use App\Service\ContentLikeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:chronicle:seed-likes',
    description: 'Применить импортированные ❤ лайки (meta callout) к like_count записей хроники',
)]
final class SeedChronicleLikesCommand extends Command
{
    public function __construct(
        private readonly ContentLikeService $likes,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Только посчитать, не писать');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $updated = $this->likes->seedImportedChronicleLikes($dryRun);

        $io->success(sprintf(
            '%s%d записей с обновлённым like_count',
            $dryRun ? 'Dry-run: ' : '',
            $updated,
        ));

        return Command::SUCCESS;
    }
}
