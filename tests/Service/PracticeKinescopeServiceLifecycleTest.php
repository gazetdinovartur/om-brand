<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\SpeakMeeting;
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

final class PracticeKinescopeServiceLifecycleTest extends KernelTestCase
{
    use PracticePaymentFixtures;

    public function testCreateSpeakForSessionStoresRoomIds(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $session = $this->createPracticeSession(
            $em,
            title: 'Speak практика',
            kind: PracticeSessionKind::Speak,
        );

        $container->set(KinescopeClient::class, new KinescopeClient(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'data' => [
                        'id' => 'room-created-1',
                        'code' => 'abc-defg-hij',
                        'link' => 'https://speak.kinescope.io/abc-defg-hij',
                    ],
                ], \JSON_THROW_ON_ERROR), ['http_code' => 201]),
            ]),
            'token',
            '',
            '11111111-1111-1111-1111-111111111111',
        ));

        /** @var PracticeKinescopeService $service */
        $service = $container->get(PracticeKinescopeService::class);
        $service->createRemoteForSession($session);

        $em->refresh($session);
        self::assertSame(PracticeSessionKind::Speak, $session->getKind());
        self::assertSame('room-created-1', $session->getKinescopeRoomId());
        self::assertSame('abc-defg-hij', $session->getKinescopeRoomCode());
    }

    public function testCreateSpeakMeetingRemoteSetsEmbedUrl(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $meeting = (new SpeakMeeting())->setTitle('Созвон');
        $em->persist($meeting);
        $em->flush();

        $container->set(KinescopeClient::class, new KinescopeClient(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'data' => [
                        'id' => 'meet-room-1',
                        'code' => 'meet-code-1',
                        'link' => 'https://speak.kinescope.io/meet-code-1',
                    ],
                ], \JSON_THROW_ON_ERROR), ['http_code' => 201]),
            ]),
            'token',
            '',
            '11111111-1111-1111-1111-111111111111',
        ));

        /** @var PracticeKinescopeService $service */
        $service = $container->get(PracticeKinescopeService::class);
        $service->createSpeakMeetingRemote($meeting);

        $em->refresh($meeting);
        self::assertSame('meet-room-1', $meeting->getKinescopeRoomId());
        self::assertSame('https://speak.kinescope.io/meet-code-1', $meeting->getSpeakEmbedUrl());
    }

    public function testFinishLiveViaApiCompletesAndDebits(): void
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

        $eventId = 'evt-fin-api-'.bin2hex(random_bytes(3));
        $videoId = 'vid-fin-api-'.bin2hex(random_bytes(3));
        $session = $this->createPracticeSession($em, status: PracticeSessionStatus::Live, eventId: $eventId);

        $container->set(KinescopeClient::class, new KinescopeClient(
            new MockHttpClient([
                new MockResponse('{}', ['http_code' => 200]), // complete
                new MockResponse(json_encode([
                    'data' => [['id' => $videoId]],
                ], \JSON_THROW_ON_ERROR)),
                new MockResponse(json_encode([
                    'data' => [
                        'id' => $videoId,
                        'title' => 'Запись',
                        'status' => 'done',
                        'duration' => 90,
                        'poster' => ['original' => 'https://cdn.example/fin.jpg'],
                    ],
                ], \JSON_THROW_ON_ERROR)),
            ]),
            'token',
        ));

        /** @var PracticeKinescopeService $service */
        $service = $container->get(PracticeKinescopeService::class);
        $service->finishLiveViaApi($session);

        $em->refresh($session);
        $em->refresh($offer);
        self::assertSame(PracticeSessionStatus::Replay, $session->getStatus());
        self::assertSame($videoId, $session->getKinescopeVideoId());
        self::assertNotNull($session->getDebitedAt());
        self::assertSame(3, $offer->getSessionsLeft());
    }
}
