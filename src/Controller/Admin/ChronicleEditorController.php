<?php

namespace App\Controller\Admin;

use App\Entity\ChronicleEntry;
use App\Repository\ChronicleEntryRepository;
use App\Repository\ChronicleEraRepository;
use App\Repository\ChronicleSeriesRepository;
use App\Repository\ChronicleTagRepository;
use App\Service\ChronicleEntryService;
use App\Service\ChroniclePublisher;
use App\Service\ChronicleUploadStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class ChronicleEditorController extends AbstractController
{
    public function __construct(
        private readonly ChronicleEntryService $entryService,
        private readonly ChronicleEntryRepository $entries,
        private readonly ChronicleEraRepository $eras,
        private readonly ChronicleSeriesRepository $series,
        private readonly ChronicleTagRepository $tags,
        private readonly ChronicleUploadStorage $uploads,
        private readonly ChroniclePublisher $publisher,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/admin/chronicle/editor/new', name: 'admin_chronicle_editor_new', methods: ['GET'])]
    public function createNew(): Response
    {
        $entry = $this->entryService->createDraft();

        return $this->redirectToRoute('admin_chronicle_editor', ['id' => $entry->getId()]);
    }

    #[Route('/admin/chronicle/editor/{id}', name: 'admin_chronicle_editor', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function edit(int $id): Response
    {
        $entry = $this->entries->find($id);
        if (!$entry instanceof ChronicleEntry) {
            throw $this->createNotFoundException('Запись не найдена.');
        }

        return $this->render('admin/chronicle/editor.html.twig', [
            'entry' => $entry,
            'entryJson' => json_encode($this->entryService->serialize($entry), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'eras' => $this->eras->findAllOrdered(),
            'seriesList' => $this->series->findAllOrdered(),
            'tags' => $this->tags->findAllOrdered(),
            'previewUrl' => $this->urlGenerator->generate('web_chronicle_preview', [
                'id' => $entry->getId(),
                'token' => $entry->getPreviewToken(),
            ]),
            'publicUrl' => $entry->isPublic()
                ? $this->urlGenerator->generate('web_chronicle_show', ['slug' => $entry->getSlug()])
                : null,
            'shortUrl' => $this->urlGenerator->generate('web_chronicle_short', ['hash' => $entry->getShortHash()]),
        ]);
    }

    #[Route('/admin/chronicle/api/{id}/autosave', name: 'admin_chronicle_autosave', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function autosave(int $id, Request $request): JsonResponse
    {
        $entry = $this->entries->find($id);
        if (!$entry instanceof ChronicleEntry) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $this->entryService->applyPayload($entry, $payload);
        $this->uploads->optimizeEntryImages($entry);

        return new JsonResponse([
            'ok' => true,
            'updatedAt' => $entry->getUpdatedAt()->format('c'),
            'readingTimeMin' => $entry->getReadingTimeMin(),
            'slug' => $entry->getSlug(),
            'data' => $this->entryService->serialize($entry),
        ]);
    }

    #[Route('/admin/chronicle/api/{id}/publish', name: 'admin_chronicle_publish', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function publish(int $id, Request $request): JsonResponse
    {
        $entry = $this->entries->find($id);
        if (!$entry instanceof ChronicleEntry) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $this->entryService->applyPayload($entry, $payload);

        $action = (string) ($payload['action'] ?? 'publish');
        if ('schedule' === $action) {
            $entry->setStatus(\App\Enum\ChronicleStatus::Scheduled);
        } else {
            $this->publisher->publishNow($entry, $entry->getPublishedAt());
        }

        $this->uploads->optimizeEntryImages($entry);

        return new JsonResponse([
            'ok' => true,
            'status' => $entry->getStatus()->value,
            'publicUrl' => $this->urlGenerator->generate('web_chronicle_show', ['slug' => $entry->getSlug()]),
            'shortUrl' => $this->urlGenerator->generate('web_chronicle_short', ['hash' => $entry->getShortHash()]),
            'data' => $this->entryService->serialize($entry),
        ]);
    }

    #[Route('/admin/chronicle/api/upload', name: 'admin_chronicle_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $kind = (string) $request->request->get('kind', 'inline');
        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['error' => 'No file'], 400);
        }

        $path = match ($kind) {
            'cover', 'og' => $this->uploads->storeCover($file),
            'gallery' => $this->uploads->storeGallery($file),
            default => $this->uploads->storeInline($file),
        };

        return new JsonResponse([
            'ok' => true,
            'path' => $path,
            'url' => match ($kind) {
                'cover', 'og' => '/uploads/chronicle/covers/'.$path,
                'gallery' => '/uploads/chronicle/gallery/'.$path,
                default => '/uploads/chronicle/inline/'.$path,
            },
        ]);
    }

    #[Route('/admin/chronicle/{id}/featured', name: 'admin_chronicle_toggle_featured', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleFeatured(int $id, Request $request, \Doctrine\ORM\EntityManagerInterface $em): JsonResponse
    {
        $entry = $this->entries->find($id);
        if (!$entry instanceof ChronicleEntry) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $token = (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token'));
        if (!$this->isCsrfTokenValid('chronicle_featured', $token)) {
            return new JsonResponse(['error' => 'Invalid CSRF'], 400);
        }

        $entry->setIsFeatured(!$entry->isFeatured());
        $entry->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        return new JsonResponse([
            'ok' => true,
            'isFeatured' => $entry->isFeatured(),
        ]);
    }
}
