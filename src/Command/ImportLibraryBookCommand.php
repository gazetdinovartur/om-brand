<?php

declare(strict_types=1);

namespace App\Command;

use App\Library\LibraryBookImportPreview;
use App\Service\LibraryBookImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:library:import',
    description: 'Идемпотентно импортирует книгу из TXT или ZIP в приватную библиотеку',
)]
final class ImportLibraryBookCommand extends Command
{
    public function __construct(
        private readonly LibraryBookImporter $importer,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'Путь к TXT или ZIP')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Название вместо найденного в файле')
            ->addOption('author', null, InputOption::VALUE_REQUIRED, 'Автор вместо найденного в файле')
            ->addOption('slug', null, InputOption::VALUE_REQUIRED, 'Стабильный slug книги')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Только разобрать и проверить, без записи в БД');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = (string) $input->getArgument('path');
        if (!$this->isAbsolutePath($path)) {
            $path = $this->projectDir.'/'.ltrim($path, '/');
        }

        $title = $this->optionalString($input->getOption('title'));
        $author = $this->optionalString($input->getOption('author'));
        $slug = $this->optionalString($input->getOption('slug'));

        try {
            if ((bool) $input->getOption('dry-run')) {
                $preview = $this->importer->preview($path, $title, $author, $slug);
                $this->writePreview($io, $preview);
                $io->success('Dry-run завершён: данные не изменены.');

                return Command::SUCCESS;
            }

            // CLI keeps silent upsert (allowReplace=true), matching previous behaviour.
            $result = $this->importer->import($path, $title, $author, $slug, allowReplace: true);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $this->writePreview($io, $result->preview);
        $io->success($result->message());

        return Command::SUCCESS;
    }

    private function writePreview(SymfonyStyle $io, LibraryBookImportPreview $preview): void
    {
        $io->definitionList(
            ['Источник' => $preview->sourceName],
            ['Книга' => sprintf('%s — %s', $preview->author, $preview->title)],
            ['Slug' => $preview->slug],
            ['Глав' => (string) $preview->chapterCount],
            ['Примечаний' => (string) $preview->footnoteCount],
            ['Знаков' => (string) $preview->totalCharacters],
            ['SHA-256' => $preview->checksum],
        );
    }

    private function optionalString(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }
        $value = trim($value);

        return '' === $value ? null : $value;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || 1 === preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }
}
