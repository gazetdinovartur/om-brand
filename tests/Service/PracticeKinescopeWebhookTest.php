<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\PaymentOfferStatus;
use App\Enum\PracticeSessionStatus;
use App\Infrastructure\Kinescope\KinescopeClient;
use App\Service\PracticeKinescopeService;
use App\Tests\Support\PracticePaymentFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PracticeKinescopeWebhookTest extends KernelTestCase
{
    use PracticePaymentFixtures;

    public function testLiveConnectedSetsLiveStatus(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $eventId = 'evt-conn-'.bin2hex(random_bytes(3));
        $session = $this->createPracticeSession($em, status: PracticeSessionStatus::Scheduled, eventId: $eventId);

        $container->set(KinescopeClient::class, new KinescopeClient(new MockHttpClient([]), 't'));
        /** @var PracticeKinescopeService $service */
        $service = $container->get(PracticeKinescopeService::class);
        $service->handleKinescopeWebhook([
            'event' => 'live.connected',
            'data' => ['event_id' => $eventId],
        ]);

        $em->refresh($session);
        self::assertSame(PracticeSessionStatus::Live, $session->getStatus());
    }

    public function testMediaUpdateEnrichesPoster(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $user = $this->createSiteUser($em);
        $inquiry = $this->createInquiry($em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($em, $inquiry, $user);
        $offer->setStatus(PaymentOfferStatus::Paid)->setPaidAt(new \DateTimeImmutable());
        $em->flush();

        $videoId = 'vid-meta-'.bin2hex(random_bytes(3));
        $session = $this->createPracticeSession(
            $em,
            status: PracticeSessionStatus::Replay,
            eventId: 'evt-meta',
            videoId: $videoId,
        );

        $container->set(KinescopeClient::class, new KinescopeClient(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'data' => [
                        'id' => $videoId,
                        'title' => 'Готовая запись',
                        'status' => 'done',
                        'duration' => 60,
                        'poster' => ['original' => 'https://cdn.example/poster.jpg'],
                    ],
                ], \JSON_THROW_ON_ERROR)),
            ]),
            'token',
        ));

        /** @var PracticeKinescopeService $service */
        $service = $container->get(PracticeKinescopeService::class);
        $service->handleKinescopeWebhook([
            'event' => 'media.update.status',
            'data' => ['id' => $videoId],
        ]);

        $em->refresh($session);
        self::assertSame('https://cdn.example/poster.jpg', $session->getPosterUrl());
        self::assertSame(60, $session->getDurationSeconds());
        self::assertNotNull($session->getRecordingReadyAt());
    }
}
