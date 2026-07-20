<?php

namespace App\Controller;

use App\Repository\ChronicleEntryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ChronicleShortLinkController extends AbstractController
{
    #[Route('/p/{hash}', name: 'web_chronicle_short', methods: ['GET'], requirements: ['hash' => '[a-z0-9]{8}'])]
    public function redirectToEntry(string $hash, ChronicleEntryRepository $entries): RedirectResponse
    {
        $entry = $entries->findByShortHash($hash);
        if (null === $entry) {
            throw new NotFoundHttpException('Ссылка не найдена.');
        }

        return $this->redirectToRoute(
            'web_chronicle_show',
            ['slug' => $entry->getSlug()],
            Response::HTTP_MOVED_PERMANENTLY,
        );
    }
}
