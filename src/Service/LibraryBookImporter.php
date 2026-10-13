<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\LibraryBook;
use App\Entity\LibraryChapter;
use App\Library\LibraryBookImportPreview;
use App\Library\LibraryBookImportResult;
use App\Library\LibraryBookParser;
use App\Library\LibrarySourceReader;
use App\Library\ParsedLibraryChapter;
use App\Repository\LibraryBookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

final class LibraryBookImporter
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LibraryBookRepository $books,
        private readonly LibrarySourceReader $sourceReader,
        private readonly LibraryBookParser $parser,
        private readonly SluggerInterface $slugger,
        private readonly LibrarySourceArchive $sourceArchive,
    ) {
    }

    public function preview(
        string $path,
        ?string $title = null,
        ?string $author = null,
        ?string $slug = null,
        ?string $sourceName = null,
    ): LibraryBookImportPreview {
        $source = $this->sourceReader->read($path, $sourceName);
        $parsed = $this->parser->parse($source, $title, $author);
        $resolvedSlug = $this->resolveSlug($slug, $parsed->title);

        return new LibraryBookImportPreview(
            $source->sourceName,
            $parsed->title,
            $parsed->author,
            $resolvedSlug,
            \count($parsed->chapters),
            \count($parsed->footnotes),
            $parsed->totalCharacters,
            $parsed->checksum,
            $parsed,
        );
    }

    public function import(
        string $path,
        ?string $title = null,
        ?string $author = null,
        ?string $slug = null,
        bool $allowReplace = false,
        ?string $sourceName = null,
    ): LibraryBookImportResult {
        $preview = $this->preview($path, $title, $author, $slug, $sourceName);
        $parsed = $preview->parsed;
        $book = $this->books->findOneBy(['slug' => $preview->slug]);
        $isNew = !$book instanceof LibraryBook;

        if (!$isNew && !$allowReplace) {
            throw new \InvalidArgumentException(sprintf(
                'Книга со slug «%s» уже есть. Отметьте «Заменить», чтобы обновить текст.',
                $preview->slug,
            ));
        }

        if ($isNew) {
            $book = new LibraryBook();
            $book->setSlug($preview->slug);
        }

        $unchanged = !$isNew
            && $book->getSourceChecksum() === $parsed->checksum
            && $book->getTitle() === $parsed->title
            && $book->getAuthor() === $parsed->author;
        if ($unchanged) {
            return new LibraryBookImportResult($book, LibraryBookImportResult::OUTCOME_UNCHANGED, $preview);
        }

        $book
            ->setTitle($parsed->title)
            ->setAuthor($parsed->author)
            ->setLanguage('ru')
            ->setSourceChecksum($parsed->checksum)
            ->setTotalCharacters($parsed->totalCharacters)
            ->setFootnotes($parsed->footnotes)
            ->setRenderMode($parsed->renderMode);

        if ($parsed->keepSourceFile) {
            $book->setSourcePath($this->sourceArchive->storeBookSource($path, $preview->slug, 'pdf'));
        } else {
            $book->setSourcePath(null);
        }

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

        return new LibraryBookImportResult(
            $book,
            $isNew ? LibraryBookImportResult::OUTCOME_CREATED : LibraryBookImportResult::OUTCOME_UPDATED,
            $preview,
        );
    }

    private function resolveSlug(?string $slug, string $title): string
    {
        $resolved = $slug ?? strtolower($this->slugger->slug($title)->toString());
        $resolved = substr(trim($resolved), 0, 120);
        if ('' === $resolved) {
            throw new \InvalidArgumentException('Не удалось построить slug; укажите slug вручную.');
        }

        return $resolved;
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
}
