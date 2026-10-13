<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\LibraryBook;
use App\Entity\LibraryChapter;
use App\Entity\ReadingBookmark;
use App\Entity\ReadingNote;
use App\Entity\ReadingProgress;
use App\Entity\ReadingQuote;
use App\Entity\SiteUser;
use App\Repository\LibraryBookRepository;
use App\Repository\LibraryChapterRepository;
use App\Repository\ReadingBookmarkRepository;
use App\Repository\ReadingNoteRepository;
use App\Repository\ReadingProgressRepository;
use App\Repository\ReadingQuoteRepository;
use App\Service\LibrarySourceArchive;
use App\Service\ReadingProgressCalculator;
use App\Service\SiteUserResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/library')]
#[IsGranted('ROLE_USER')]
final class LibraryController extends AbstractController
{
    public function __construct(
        private readonly LibraryBookRepository $books,
        private readonly LibraryChapterRepository $chapters,
        private readonly ReadingProgressRepository $progress,
        private readonly ReadingBookmarkRepository $bookmarks,
        private readonly ReadingQuoteRepository $quotes,
        private readonly ReadingNoteRepository $notes,
        private readonly ReadingProgressCalculator $progressCalculator,
        private readonly SiteUserResolver $siteUsers,
        private readonly LibrarySourceArchive $sourceArchive,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('', name: 'web_library', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->currentSiteUser();
        $books = $this->books->findAllOrdered();
        $progressByBook = $this->progress->findIndexedForUser($user, $books);
        $bookmarkCounts = $this->bookmarks->countIndexedForUser($user, $books);
        $quoteCounts = $this->quotes->countIndexedForUser($user, $books);
        $noteCounts = $this->notes->countIndexedForUser($user, $books);
        $items = [];

        foreach ($books as $book) {
            $bookId = (int) $book->getId();
            $readingProgress = $progressByBook[$bookId] ?? null;
            $items[] = [
                'book' => $book,
                'progress' => $readingProgress,
                'progressPercent' => $readingProgress?->getProgressPercent() ?? 0.0,
                'bookmarkCount' => $bookmarkCounts[$bookId] ?? 0,
                'quoteCount' => $quoteCounts[$bookId] ?? 0,
                'noteCount' => $noteCounts[$bookId] ?? 0,
            ];
        }

        return $this->privateResponse($this->render('web/library/index.html.twig', [
            'items' => $items,
            'siteUser' => $user,
        ]));
    }

    #[Route('/{bookSlug}/continue', name: 'web_library_continue', methods: ['GET'])]
    public function continueReading(string $bookSlug): RedirectResponse
    {
        $book = $this->findBook($bookSlug);
        $readingProgress = $this->progress->findForUserAndBook($this->currentSiteUser(), $book);
        $chapter = $readingProgress?->getChapter() ?? $book->getFirstChapter();
        if (!$chapter instanceof LibraryChapter) {
            throw $this->createNotFoundException('В книге пока нет глав.');
        }

        $parameters = [
            'bookSlug' => $book->getSlug(),
            'chapterSlug' => $chapter->getSlug(),
        ];
        if (null !== $readingProgress && $this->isValidProgressBlock($book, $chapter, $readingProgress->getBlockId())) {
            $parameters['_fragment'] = $readingProgress->getBlockId();
        }

        return $this->privateResponse($this->redirectToRoute('web_library_read', $parameters));
    }

    #[Route('/{bookSlug}/read/{chapterSlug}', name: 'web_library_read', methods: ['GET'])]
    public function read(string $bookSlug, string $chapterSlug, Request $request): Response
    {
        $user = $this->currentSiteUser();
        $book = $this->findBook($bookSlug);
        $chapter = $this->findChapter($book, $chapterSlug);
        $readingProgress = $this->progress->findForUserAndBook($user, $book);
        $bookmarks = $this->bookmarks->findForUserAndBook($user, $book);
        $quotes = $this->quotes->findForUserAndBook($user, $book);
        $notes = $this->notes->findForUserAndBook($user, $book);
        $bookmarkedBlockIds = [];
        foreach ($bookmarks as $bookmark) {
            if ($bookmark->getChapter()->getId() === $chapter->getId()) {
                $bookmarkedBlockIds[$bookmark->getBlockId()] = true;
            }
        }
        $quotedBlockIds = [];
        foreach ($quotes as $quote) {
            if ($quote->getChapter()->getId() === $chapter->getId()) {
                $quotedBlockIds[$quote->getBlockId()] = true;
            }
        }
        $notedBlockIds = [];
        $quotesByBlock = [];
        $notesByBlock = [];
        foreach ($quotes as $quote) {
            if ($quote->getChapter()->getId() === $chapter->getId()) {
                $quotesByBlock[$quote->getBlockId()][] = $quote;
            }
        }
        foreach ($notes as $note) {
            if ($note->getChapter()->getId() === $chapter->getId()) {
                $notedBlockIds[$note->getBlockId()] = true;
                $notesByBlock[$note->getBlockId()][] = $note;
            }
        }

        $footnotes = [];
        foreach ($chapter->referencedFootnoteNumbers() as $number) {
            $text = $book->getFootnote($number);
            if (null === $text) {
                continue;
            }
            $footnotes[] = [
                'number' => $number,
                'text' => $text,
                'referenceId' => $this->firstReferenceId($chapter, $number),
            ];
        }

        $pdfPage = 1;
        if ($book->isPdfFallback() && null !== $readingProgress) {
            if (1 === preg_match('/^pdf-p-(\d+)$/', $readingProgress->getBlockId(), $m)) {
                $pdfPage = max(1, (int) $m[1]);
            }
        }

        return $this->privateResponse($this->render('web/library/read.html.twig', [
            'book' => $book,
            'chapter' => $chapter,
            'previousChapter' => $this->chapters->findAdjacent($chapter, -1),
            'nextChapter' => $this->chapters->findAdjacent($chapter, 1),
            'readingProgress' => $readingProgress,
            'bookmarks' => $bookmarks,
            'quotes' => $quotes,
            'notes' => $notes,
            'bookmarkedBlockIds' => $bookmarkedBlockIds,
            'quotedBlockIds' => $quotedBlockIds,
            'notedBlockIds' => $notedBlockIds,
            'quotesByBlock' => $quotesByBlock,
            'notesByBlock' => $notesByBlock,
            'footnotes' => $footnotes,
            'pdfPage' => $pdfPage,
            'openAtEnd' => 'end' === $request->query->getString('at'),
            'progressCsrfToken' => $this->csrfTokenManager
                ->getToken('library_progress_'.$book->getId())->getValue(),
            'bookmarkCsrfToken' => $this->csrfTokenManager
                ->getToken('library_bookmark_'.$book->getId())->getValue(),
            'quoteCsrfToken' => $this->csrfTokenManager
                ->getToken('library_quote_'.$book->getId())->getValue(),
            'noteCsrfToken' => $this->csrfTokenManager
                ->getToken('library_note_'.$book->getId())->getValue(),
        ]));
    }

    #[Route('/{bookSlug}/pdf', name: 'web_library_pdf', methods: ['GET'])]
    public function pdf(string $bookSlug): Response
    {
        $book = $this->findBook($bookSlug);
        if (!$book->isPdfFallback() || null === $book->getSourcePath()) {
            throw $this->createNotFoundException('PDF недоступен.');
        }
        $path = $this->sourceArchive->absolutePath($book->getSourcePath());
        if (null === $path) {
            throw $this->createNotFoundException('Файл PDF не найден.');
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $book->getSlug().'.pdf');

        return $this->privateResponse($response);
    }

    #[Route('/{bookSlug}/offline-pack', name: 'web_library_offline_pack', methods: ['GET'])]
    public function offlinePack(string $bookSlug): JsonResponse
    {
        $user = $this->currentSiteUser();
        $book = $this->findBook($bookSlug);
        $quotes = $this->quotes->findForUserAndBook($user, $book);
        $notes = $this->notes->findForUserAndBook($user, $book);
        $bookmarks = $this->bookmarks->findForUserAndBook($user, $book);
        $progress = $this->progress->findForUserAndBook($user, $book);

        $chapters = [];
        foreach ($book->getChapters() as $chapter) {
            $chapters[] = [
                'slug' => $chapter->getSlug(),
                'title' => $chapter->getTitle(),
                'sortOrder' => $chapter->getSortOrder(),
                'blocks' => $chapter->getBlocks(),
            ];
        }

        return $this->privateJson([
            'ok' => true,
            'book' => [
                'slug' => $book->getSlug(),
                'title' => $book->getTitle(),
                'author' => $book->getAuthor(),
                'renderMode' => $book->getRenderMode(),
                'footnotes' => $book->getFootnotes(),
            ],
            'chapters' => $chapters,
            'progress' => null === $progress ? null : [
                'chapter' => $progress->getChapter()->getSlug(),
                'block' => $progress->getBlockId(),
                'progressPercent' => $progress->getProgressPercent(),
            ],
            'bookmarks' => array_map(static fn (ReadingBookmark $b): array => [
                'id' => $b->getId(),
                'chapter' => $b->getChapter()->getSlug(),
                'block' => $b->getBlockId(),
                'excerpt' => $b->getExcerpt(),
            ], $bookmarks),
            'quotes' => array_map(static fn (ReadingQuote $q): array => [
                'id' => $q->getId(),
                'chapter' => $q->getChapter()->getSlug(),
                'block' => $q->getBlockId(),
                'excerpt' => $q->getExcerpt(),
                'startOffset' => $q->getStartOffset(),
                'endOffset' => $q->getEndOffset(),
            ], $quotes),
            'notes' => array_map(static fn (ReadingNote $n): array => [
                'id' => $n->getId(),
                'chapter' => $n->getChapter()->getSlug(),
                'block' => $n->getBlockId(),
                'body' => $n->getBody(),
            ], $notes),
            'generatedAt' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ]);
    }

    #[Route('/{bookSlug}/progress', name: 'web_library_progress', methods: ['POST'])]
    public function saveProgress(string $bookSlug, Request $request): JsonResponse
    {
        $user = $this->currentSiteUser();
        $book = $this->findBook($bookSlug);
        $payload = $request->getPayload();
        if (!$this->isCsrfTokenValid('library_progress_'.$book->getId(), $payload->getString('_token'))) {
            return $this->privateJson(['ok' => false, 'message' => 'Сессия устарела. Обновите страницу.'], 403);
        }

        $chapter = $this->findChapter($book, $payload->getString('chapter'));
        $blockId = $payload->getString('block');
        if ('' === $blockId || !$this->isValidProgressBlock($book, $chapter, $blockId)) {
            return $this->privateJson(['ok' => false, 'message' => 'Место чтения не найдено.'], 422);
        }

        $readingProgress = $this->progress->findForUserAndBook($user, $book) ?? new ReadingProgress();
        $readingProgress
            ->setSiteUser($user)
            ->setBook($book)
            ->setChapter($chapter)
            ->setBlockId($blockId)
            ->setProgressBasisPoints($this->progressCalculator->calculateBasisPoints($book, $chapter, $blockId))
            ->markOpened();

        $this->entityManager->persist($readingProgress);
        $this->entityManager->flush();

        return $this->privateJson([
            'ok' => true,
            'block' => $blockId,
            'progressPercent' => $readingProgress->getProgressPercent(),
            'savedAt' => $readingProgress->getUpdatedAt()->format(\DATE_ATOM),
        ]);
    }

    #[Route('/{bookSlug}/bookmarks', name: 'web_library_bookmark', methods: ['POST'])]
    public function changeBookmark(string $bookSlug, Request $request): JsonResponse
    {
        $user = $this->currentSiteUser();
        $book = $this->findBook($bookSlug);
        $payload = $request->getPayload();
        if (!$this->isCsrfTokenValid('library_bookmark_'.$book->getId(), $payload->getString('_token'))) {
            return $this->privateJson(['ok' => false, 'message' => 'Сессия устарела. Обновите страницу.'], 403);
        }

        if ('remove' === $payload->getString('action')) {
            $bookmark = $this->bookmarks->find($payload->getInt('bookmarkId'));
            if (
                !$bookmark instanceof ReadingBookmark
                || $bookmark->getSiteUser()->getId() !== $user->getId()
                || $bookmark->getBook()->getId() !== $book->getId()
            ) {
                throw $this->createNotFoundException('Закладка не найдена.');
            }
            $this->entityManager->remove($bookmark);
            $this->entityManager->flush();

            return $this->privateJson(['ok' => true, 'bookmarked' => false]);
        }

        $chapter = $this->findChapter($book, $payload->getString('chapter'));
        $blockId = $payload->getString('block');
        $block = $chapter->findBlock($blockId);
        if (null === $block) {
            return $this->privateJson(['ok' => false, 'message' => 'Место для закладки не найдено.'], 422);
        }

        $bookmark = $this->bookmarks->findAtLocator($user, $book, $chapter, $blockId);
        if ($bookmark instanceof ReadingBookmark) {
            $this->entityManager->remove($bookmark);
            $this->entityManager->flush();

            return $this->privateJson(['ok' => true, 'bookmarked' => false, 'block' => $blockId]);
        }

        $bookmark = new ReadingBookmark();
        $bookmark
            ->setSiteUser($user)
            ->setBook($book)
            ->setChapter($chapter)
            ->setBlockId($blockId)
            ->setExcerpt($this->excerpt($block['text']));
        $this->entityManager->persist($bookmark);
        $this->entityManager->flush();

        return $this->privateJson([
            'ok' => true,
            'bookmarked' => true,
            'bookmarkId' => $bookmark->getId(),
            'block' => $blockId,
            'excerpt' => $bookmark->getExcerpt(),
        ]);
    }

    #[Route('/{bookSlug}/quotes', name: 'web_library_quote', methods: ['POST'])]
    public function changeQuote(string $bookSlug, Request $request): JsonResponse
    {
        $user = $this->currentSiteUser();
        $book = $this->findBook($bookSlug);
        $payload = $request->getPayload();
        if (!$this->isCsrfTokenValid('library_quote_'.$book->getId(), $payload->getString('_token'))) {
            return $this->privateJson(['ok' => false, 'message' => 'Сессия устарела. Обновите страницу.'], 403);
        }

        if ('remove' === $payload->getString('action')) {
            $quote = $this->quotes->find($payload->getInt('quoteId'));
            if (
                !$quote instanceof ReadingQuote
                || $quote->getSiteUser()->getId() !== $user->getId()
                || $quote->getBook()->getId() !== $book->getId()
            ) {
                throw $this->createNotFoundException('Цитата не найдена.');
            }
            $this->entityManager->remove($quote);
            $this->entityManager->flush();

            return $this->privateJson(['ok' => true, 'removed' => true]);
        }

        $chapter = $this->findChapter($book, $payload->getString('chapter'));
        $blockId = $payload->getString('block');
        $block = $chapter->findBlock($blockId);
        if (null === $block) {
            return $this->privateJson(['ok' => false, 'message' => 'Место для цитаты не найдено.'], 422);
        }

        $excerpt = trim($payload->getString('excerpt'));
        if ('' === $excerpt) {
            $excerpt = $this->excerpt($block['text']);
        } else {
            $excerpt = $this->excerpt($excerpt);
        }

        $quote = new ReadingQuote();
        $quote
            ->setSiteUser($user)
            ->setBook($book)
            ->setChapter($chapter)
            ->setBlockId($blockId)
            ->setStartOffset(max(0, $payload->getInt('startOffset')))
            ->setEndOffset(max(0, $payload->getInt('endOffset')))
            ->setExcerpt($excerpt);
        $this->entityManager->persist($quote);
        $this->entityManager->flush();

        return $this->privateJson([
            'ok' => true,
            'quoteId' => $quote->getId(),
            'block' => $blockId,
            'excerpt' => $quote->getExcerpt(),
        ]);
    }

    #[Route('/{bookSlug}/notes', name: 'web_library_note', methods: ['POST'])]
    public function changeNote(string $bookSlug, Request $request): JsonResponse
    {
        $user = $this->currentSiteUser();
        $book = $this->findBook($bookSlug);
        $payload = $request->getPayload();
        if (!$this->isCsrfTokenValid('library_note_'.$book->getId(), $payload->getString('_token'))) {
            return $this->privateJson(['ok' => false, 'message' => 'Сессия устарела. Обновите страницу.'], 403);
        }

        if ('remove' === $payload->getString('action')) {
            $note = $this->notes->find($payload->getInt('noteId'));
            if (
                !$note instanceof ReadingNote
                || $note->getSiteUser()->getId() !== $user->getId()
                || $note->getBook()->getId() !== $book->getId()
            ) {
                throw $this->createNotFoundException('Заметка не найдена.');
            }
            $this->entityManager->remove($note);
            $this->entityManager->flush();

            return $this->privateJson(['ok' => true, 'removed' => true]);
        }

        $chapter = $this->findChapter($book, $payload->getString('chapter'));
        $blockId = $payload->getString('block');
        if (null === $chapter->findBlock($blockId)) {
            return $this->privateJson(['ok' => false, 'message' => 'Место для заметки не найдено.'], 422);
        }

        $body = trim($payload->getString('body'));
        if ('' === $body) {
            return $this->privateJson(['ok' => false, 'message' => 'Введите текст заметки.'], 422);
        }
        if (mb_strlen($body) > 4000) {
            return $this->privateJson(['ok' => false, 'message' => 'Заметка слишком длинная.'], 422);
        }

        $note = new ReadingNote();
        $note
            ->setSiteUser($user)
            ->setBook($book)
            ->setChapter($chapter)
            ->setBlockId($blockId)
            ->setBody($body);
        $this->entityManager->persist($note);
        $this->entityManager->flush();

        return $this->privateJson([
            'ok' => true,
            'noteId' => $note->getId(),
            'block' => $blockId,
            'body' => $note->getBody(),
        ]);
    }

    private function isValidProgressBlock(LibraryBook $book, LibraryChapter $chapter, string $blockId): bool
    {
        if ($book->isPdfFallback() && 1 === preg_match('/^pdf-p-\d+$/', $blockId)) {
            return true;
        }

        return $chapter->hasBlock($blockId);
    }

    private function currentSiteUser(): SiteUser
    {
        $user = $this->siteUsers->resolve($this->getUser());
        if (!$user instanceof SiteUser) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function findBook(string $slug): LibraryBook
    {
        $book = $this->books->findOneBy(['slug' => $slug]);
        if (!$book instanceof LibraryBook) {
            throw $this->createNotFoundException('Книга не найдена.');
        }

        return $book;
    }

    private function findChapter(LibraryBook $book, string $slug): LibraryChapter
    {
        $chapter = $this->chapters->findOneBy(['book' => $book, 'slug' => $slug]);
        if (!$chapter instanceof LibraryChapter) {
            throw $this->createNotFoundException('Глава не найдена.');
        }

        return $chapter;
    }

    private function privateResponse(Response $response): Response
    {
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }

    /** @param array<string, mixed> $data */
    private function privateJson(array $data, int $status = 200): JsonResponse
    {
        return $this->privateResponse(new JsonResponse($data, $status));
    }

    private function excerpt(string $text): string
    {
        $text = preg_replace('/\[\[note:\d+]]/', '', $text) ?? $text;
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        $length = iconv_strlen($text, 'UTF-8');
        if (false !== $length && $length > 240) {
            $short = iconv_substr($text, 0, 237, 'UTF-8');
            $text = (false === $short ? substr($text, 0, 237) : rtrim($short)).'…';
        }

        return $text;
    }

    private function firstReferenceId(LibraryChapter $chapter, int $number): ?string
    {
        $needle = sprintf('[[note:%d]]', $number);
        foreach ($chapter->getBlocks() as $block) {
            if (str_contains($block['text'], $needle)) {
                return sprintf('note-ref-%d-%s', $number, $block['id']);
            }
        }

        return null;
    }
}
