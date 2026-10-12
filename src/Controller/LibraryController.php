<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\LibraryBook;
use App\Entity\LibraryChapter;
use App\Entity\ReadingBookmark;
use App\Entity\ReadingProgress;
use App\Entity\SiteUser;
use App\Repository\LibraryBookRepository;
use App\Repository\LibraryChapterRepository;
use App\Repository\ReadingBookmarkRepository;
use App\Repository\ReadingProgressRepository;
use App\Service\ReadingProgressCalculator;
use App\Service\SiteUserResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        private readonly ReadingProgressCalculator $progressCalculator,
        private readonly SiteUserResolver $siteUsers,
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
        $items = [];

        foreach ($books as $book) {
            $bookId = (int) $book->getId();
            $readingProgress = $progressByBook[$bookId] ?? null;
            $items[] = [
                'book' => $book,
                'progress' => $readingProgress,
                'progressPercent' => $readingProgress?->getProgressPercent() ?? 0.0,
                'bookmarkCount' => $bookmarkCounts[$bookId] ?? 0,
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
        if (null !== $readingProgress && $chapter->hasBlock($readingProgress->getBlockId())) {
            $parameters['_fragment'] = $readingProgress->getBlockId();
        }

        return $this->privateResponse($this->redirectToRoute('web_library_read', $parameters));
    }

    #[Route('/{bookSlug}/read/{chapterSlug}', name: 'web_library_read', methods: ['GET'])]
    public function read(string $bookSlug, string $chapterSlug): Response
    {
        $user = $this->currentSiteUser();
        $book = $this->findBook($bookSlug);
        $chapter = $this->findChapter($book, $chapterSlug);
        $readingProgress = $this->progress->findForUserAndBook($user, $book);
        $bookmarks = $this->bookmarks->findForUserAndBook($user, $book);
        $bookmarkedBlockIds = [];
        foreach ($bookmarks as $bookmark) {
            if ($bookmark->getChapter()->getId() === $chapter->getId()) {
                $bookmarkedBlockIds[$bookmark->getBlockId()] = true;
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

        return $this->privateResponse($this->render('web/library/read.html.twig', [
            'book' => $book,
            'chapter' => $chapter,
            'previousChapter' => $this->chapters->findAdjacent($chapter, -1),
            'nextChapter' => $this->chapters->findAdjacent($chapter, 1),
            'readingProgress' => $readingProgress,
            'bookmarks' => $bookmarks,
            'bookmarkedBlockIds' => $bookmarkedBlockIds,
            'footnotes' => $footnotes,
            'progressCsrfToken' => $this->csrfTokenManager
                ->getToken('library_progress_'.$book->getId())->getValue(),
            'bookmarkCsrfToken' => $this->csrfTokenManager
                ->getToken('library_bookmark_'.$book->getId())->getValue(),
        ]));
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
        if ('' === $blockId || !$chapter->hasBlock($blockId)) {
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
