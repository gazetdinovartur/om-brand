<?php

namespace App\Controller\Admin;

use App\Entity\CaseStudy;
use App\Repository\CaseStudyRepository;
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
final class CaseStudyReorderController extends AbstractController
{
    public function __construct(
        private readonly CaseStudyRepository $cases,
        private readonly EntityManagerInterface $em,
        private readonly CsrfTokenManagerInterface $csrf,
    ) {
    }

    #[Route('/admin/cases/reorder', name: 'admin_case_reorder', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['ok' => false, 'message' => 'Некорректный запрос'], Response::HTTP_BAD_REQUEST);
        }

        $token = (string) ($payload['_token'] ?? '');
        if (!$this->csrf->isTokenValid(new CsrfToken('case_reorder', $token))) {
            return $this->json(['ok' => false, 'message' => 'CSRF'], Response::HTTP_FORBIDDEN);
        }

        /** @var list<int|string> $ids */
        $ids = $payload['ids'] ?? [];
        if ([] === $ids) {
            return $this->json(['ok' => false, 'message' => 'Пустой список'], Response::HTTP_BAD_REQUEST);
        }

        $order = 0;
        foreach ($ids as $id) {
            $case = $this->cases->find((int) $id);
            if (!$case instanceof CaseStudy) {
                continue;
            }
            $case->setSortOrder($order);
            ++$order;
        }

        $this->em->flush();

        return $this->json(['ok' => true]);
    }
}
