<?php

namespace App\Controller;

use App\Content\HouseContent;
use App\Repository\CaseStudyRepository;
use App\Service\PublicSiteContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'web_home', methods: ['GET'])]
    public function index(
        PublicSiteContext $siteContext,
        UrlGeneratorInterface $urlGenerator,
        CaseStudyRepository $caseStudyRepository,
    ): Response {
        $settings = $siteContext->getSettings();
        $hasCases = $caseStudyRepository->countPublished() > 0;
        $rooms = [];
        foreach (HouseContent::mapRooms() as $room) {
            if (isset($room['external'])) {
                $href = $room['external'];
                $external = true;
            } else {
                $href = $urlGenerator->generate(
                    $room['route'],
                    $room['routeParams'] ?? [],
                );
                $external = false;
            }

            $active = true;
            $invite = $room['invite'];
            if ('cases' === $room['id'] && !$hasCases) {
                $active = false;
                $invite = 'Скоро здесь будут кейсы';
                $href = null;
            }

            $rooms[] = [
                ...$room,
                'href' => $href,
                'external' => $external,
                'active' => $active,
                'invite' => $invite,
            ];
        }

        return $this->render('web/home/index.html.twig', [
            'settings' => $settings,
            'heroGreeting' => HouseContent::heroGreeting(),
            'heroLead' => HouseContent::heroLead(),
            'mapRooms' => $rooms,
        ]);
    }
}
