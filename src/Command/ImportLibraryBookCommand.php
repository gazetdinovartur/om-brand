<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\LibraryBook;
use App\Entity\LibraryChapter;
use App\Library\LibrarySourceReader;
use App\Library\LibraryTextParser;
use App\Library\ParsedLibraryChapter;
use App\Repository\LibraryBookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'app:library:import',
    description: 'Идемпотентно импортирует книгу из TXT или ZIP в приватную библиотеку',
)]
final class ImportLibraryBookCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LibraryBookRepository $books,
        private readonly LibrarySourceReader $sourceReader,
        private readonly LibraryTextParser $parser,
        private readonly SluggerInterface $slugger,
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

        try {
            $source = $this->sourceReader->read($path);
            $parsed = $this->parser->parse(
                $source,
                $this->optionalString($input->getOption('title')),
                $this->optionalString($input->getOption('author')),
            );
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $slugOption = $this->optionalString($input->getOption('slug'));
        $slug = $slugOption ?? strtolower($this->slugger->slug($parsed->title)->toString());
        $slug = substr($slug, 0, 120);
        if ('' === $slug) {
            $io->error('Не удалось построить slug; передайте --slug.');

            return Command::FAILURE;
        }

        $io->definitionList(
            ['Источник' => $source->sourceName],
            ['Книга' => sprintf('%s — %s', $parsed->author, $parsed->title)],
            ['Slug' => $slug],
            ['Глав' => \count($parsed->chapters)],
            ['Примечаний' => \count($parsed->footnotes)],
            ['Знаков' => $parsed->totalCharacters],
            ['SHA-256' => $parsed->checksum],
        );

        if ((bool) $input->getOption('dry-run')) {
            $io->success('Dry-run завершён: данные не изменены.');

            return Command::SUCCESS;
        }

        $book = $this->books->findOneBy(['slug' => $slug]);
        $isNew = !$book instanceof LibraryBook;
        if ($isNew) {
            $book = new LibraryBook();
            $book->setSlug($slug);
        }

        $unchanged = !$isNew
            && $book->getSourceChecksum() === $parsed->checksum
            && $book->getTitle() === $parsed->title
            && $book->getAuthor() === $parsed->author;
        if ($unchanged) {
            $io->success('Источник уже импортирован, изменений нет.');

            return Command::SUCCESS;
        }

        $book
            ->setTitle($parsed->title)
            ->setAuthor($parsed->author)
            ->setLanguage('ru')
            ->setSourceChecksum($parsed->checksum)
            ->setTotalCharacters($parsed->totalCharacters)
            ->setFootnotes($parsed->footnotes);

        $existingBySlug = [];
        foreach ($book->getChapters() as $chapter) {
            $existingBySlug[$chapter->getSlug()] = $chapter;
        }

        $seen = [];
        foreach ($parsed->chapters as $parsedChapter) {
            $chapter = $existingBySlug[$parsedChapter->slug] ?? new LibraryChapter();
            $this->updateChapter($chapter, $parsedChapter);
            $book->addChapter($chapter);
            $seen[$parsedChapter->slug] = true;
        }
        foreach ($book->getChapters()->toArray() as $chapter) {
            if (!isset($seen[$chapter->getSlug()])) {
                $book->removeChapter($chapter);
            }
        }

        $this->entityManager->persist($book);
        $this->entityManager->flush();

        $io->success($isNew ? 'Книга импортирована.' : 'Книга обновлена.');

        return Command::SUCCESS;
    }

    private function updateChapter(LibraryChapter $chapter, ParsedLibraryChapter $parsed): void
    {
        $chapter
            ->setTitle($parsed->title)
            ->setSlug($parsed->slug)
            ->setSortOrder($parsed->sortOrder)
            ->setBlocks($parsed->blocks)
            ->setCharacterCount($parsed->characterCount);
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
