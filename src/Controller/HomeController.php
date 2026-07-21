<?php

namespace App\Controller;

use App\Content\HouseContent;
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
    ): Response {
        $settings = $siteContext->getSettings();
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
            $rooms[] = [
                ...$room,
                'href' => $href,
                'external' => $external,
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
