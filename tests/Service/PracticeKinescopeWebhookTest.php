<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\PaymentOfferStatus;
use App\Enum\PracticeSessionKind;
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

    public function testSpeakCallStartedSetsLive(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $roomId = 'room-start-'.bin2hex(random_bytes(3));
        $session = $this->createPracticeSession(
            $em,
            status: PracticeSessionStatus::Scheduled,
            kind: PracticeSessionKind::Speak,
            roomId: $roomId,
            roomCode: 'abc-code',
        );

        $container->set(KinescopeClient::class, new KinescopeClient(new MockHttpClient([]), 't'));
        /** @var PracticeKinescopeService $service */
        $service = $container->get(PracticeKinescopeService::class);
        $service->handleKinescopeWebhook([
            'event' => 'speak.call.started',
            'data' => ['room_id' => $roomId],
        ]);

        $em->refresh($session);
        self::assertSame(PracticeSessionStatus::Live, $session->getStatus());
    }

    public function testSpeakRecordFinishedDebitsOnce(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $user = $this->createSiteUser($em);
        $inquiry = $this->createInquiry($em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($em, $inquiry, $user, amountKopecks: 555_00);
        $offer->setStatus(PaymentOfferStatus::Paid)->setPaidAt(new \DateTimeImmutable());
        $em->flush();

        $roomId = 'room-fin-'.bin2hex(random_bytes(3));
        $videoId = 'vid-speak-'.bin2hex(random_bytes(3));
        $session = $this->createPracticeSession(
            $em,
            status: PracticeSessionStatus::Live,
            kind: PracticeSessionKind::Speak,
            roomId: $roomId,
            roomCode: 'fin-code',
        );

        $container->set(KinescopeClient::class, new KinescopeClient(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'data' => [
                        'id' => $videoId,
                        'title' => 'Speak запись',
                        'status' => 'done',
                        'duration' => 42,
                        'poster' => ['original' => 'https://cdn.example/speak.jpg'],
                    ],
                ], \JSON_THROW_ON_ERROR)),
            ]),
            'token',
        ));

        /** @var PracticeKinescopeService $service */
        $service = $container->get(PracticeKinescopeService::class);
        $service->handleKinescopeWebhook([
            'event' => 'speak.record.finished',
            'data' => [
                'room_id' => $roomId,
                'video_id' => $videoId,
            ],
        ]);

        $em->refresh($session);
        $em->refresh($offer);
        self::assertSame(PracticeSessionStatus::Replay, $session->getStatus());
        self::assertSame($videoId, $session->getKinescopeVideoId());
        self::assertNotNull($session->getDebitedAt());
        self::assertSame(3, $offer->getSessionsLeft());
    }

    public function testLiveCancelledMarksCompletedWithoutDebit(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $user = $this->createSiteUser($em);
        $inquiry = $this->createInquiry($em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($em, $inquiry, $user, amountKopecks: 555_00);
        $offer->setStatus(PaymentOfferStatus::Paid)->setPaidAt(new \DateTimeImmutable());
        $em->flush();

        $eventId = 'evt-cancel-'.bin2hex(random_bytes(3));
        $session = $this->createPracticeSession($em, status: PracticeSessionStatus::Live, eventId: $eventId);

        $container->set(KinescopeClient::class, new KinescopeClient(new MockHttpClient([]), 't'));
        /** @var PracticeKinescopeService $service */
        $service = $container->get(PracticeKinescopeService::class);
        $service->handleKinescopeWebhook([
            'event' => 'live.cancelled',
            'data' => ['event_id' => $eventId],
        ]);

        $em->refresh($session);
        $em->refresh($offer);
        self::assertSame(PracticeSessionStatus::Completed, $session->getStatus());
        self::assertNull($session->getDebitedAt());
        self::assertSame(4, $offer->getSessionsLeft());
    }
}
