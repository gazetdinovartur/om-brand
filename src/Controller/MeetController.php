<?php

declare(strict_types=1);

namespace App\Controller;

use App\Infrastructure\Kinescope\KinescopeSpeakJwt;
use App\Repository\SpeakMeetingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MeetController extends AbstractController
{
    #[Route('/meet/{token}', name: 'web_speak_meet', methods: ['GET'], requirements: ['token' => '[0-9a-fA-F\\-]{36}'])]
    public function show(string $token, SpeakMeetingRepository $meetings, KinescopeSpeakJwt $speakJwt): Response
    {
        $meeting = $meetings->findOneByToken($token);
        if (null === $meeting || null === $meeting->getSpeakEmbedUrl()) {
            throw $this->createNotFoundException('Встреча не найдена или комната ещё не создана.');
        }

        $embedUrl = $meeting->getSpeakEmbedUrl();
        $roomCode = $meeting->getKinescopeRoomCode();
        if (null !== $roomCode && $this->isGranted('ROLE_ADMIN') && $speakJwt->isConfigured()) {
            $embedUrl = $speakJwt->buildAdminEmbedUrl($roomCode);
        }

        $response = $this->render('web/meet/show.html.twig', [
            'meeting' => $meeting,
            'embedUrl' => $embedUrl,
            'isSpeakHost' => $this->isGranted('ROLE_ADMIN') && $speakJwt->isConfigured(),
        ]);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }
}
