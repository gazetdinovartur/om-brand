<?php

namespace App\Command;

use App\Repository\ChronicleEntryRepository;
use App\Service\NewPostNotifier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:chronicle:notify',
    description: 'Send new-post email/push notifications for an already published chronicle entry (re-notify / smoke test)',
)]
final class NotifyChronicleSubscribersCommand extends Command
{
    public function __construct(
        private readonly ChronicleEntryRepository $entries,
        private readonly NewPostNotifier $notifier,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('slug', InputArgument::REQUIRED, 'Published entry slug');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $slug = (string) $input->getArgument('slug');
        $entry = $this->entries->findOneBy(['slug' => $slug]);

        if (null === $entry) {
            $io->error(sprintf('Entry "%s" not found.', $slug));

            return Command::FAILURE;
        }

        if (!$entry->isVisibleInFeed()) {
            $io->warning('Entry is not visible in the public feed; notifier will no-op.');
        }

        $this->notifier->notifyPublished($entry);
        $io->success(sprintf('Notifications dispatched for "%s".', $entry->getTitle()));

        return Command::SUCCESS;
    }
}
