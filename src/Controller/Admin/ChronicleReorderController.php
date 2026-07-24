<?php

namespace App\Controller\Admin;

use App\Repository\ChronicleEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class ChronicleReorderController extends AbstractController
{
    public function __construct(
        private readonly ChronicleEntryRepository $entries,
        private readonly EntityManagerInterface $em,
        private readonly CsrfTokenManagerInterface $csrf,
    ) {
    }

    #[Route('/admin/chronicle/reorder', name: 'admin_chronicle_reorder', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['ok' => false, 'message' => 'Некорректный запрос'], Response::HTTP_BAD_REQUEST);
        }

        $token = (string) ($payload['_token'] ?? '');
        if (!$this->csrf->isTokenValid(new CsrfToken('chronicle_reorder', $token))) {
            return $this->json(['ok' => false, 'message' => 'CSRF'], Response::HTTP_FORBIDDEN);
        }

        /** @var list<int|string> $rawIds */
        $rawIds = $payload['ids'] ?? [];
        if ([] === $rawIds) {
            return $this->json(['ok' => false, 'message' => 'Пустой список'], Response::HTTP_BAD_REQUEST);
        }

        $orderedVisible = [];
        foreach ($rawIds as $id) {
            $intId = (int) $id;
            if ($intId > 0 && !\in_array($intId, $orderedVisible, true)) {
                $orderedVisible[] = $intId;
            }
        }

        if ([] === $orderedVisible) {
            return $this->json(['ok' => false, 'message' => 'Пустой список'], Response::HTTP_BAD_REQUEST);
        }

        $allIds = $this->entries->findOrderedIds();
        $visibleSet = array_fill_keys($orderedVisible, true);

        // Keep only ids that still exist; ignore stale client ids.
        $orderedVisible = array_values(array_filter(
            $orderedVisible,
            static fn (int $id): bool => \in_array($id, $allIds, true),
        ));

        if ([] === $orderedVisible) {
            return $this->json(['ok' => false, 'message' => 'Записи не найдены'], Response::HTTP_BAD_REQUEST);
        }

        $visibleSet = array_fill_keys($orderedVisible, true);
        $next = 0;
        $merged = [];
        foreach ($allIds as $id) {
            if (isset($visibleSet[$id])) {
                $merged[] = $orderedVisible[$next];
                ++$next;
            } else {
                $merged[] = $id;
            }
        }

        // Append any visible ids that somehow weren't in the global list (shouldn't happen).
        while ($next < \count($orderedVisible)) {
            $merged[] = $orderedVisible[$next];
            ++$next;
        }

        foreach ($merged as $order => $id) {
            $entry = $this->entries->find($id);
            if (null === $entry) {
                continue;
            }
            $entry->setSortOrder($order);
        }

        $this->em->flush();

        return $this->json(['ok' => true]);
    }
}
