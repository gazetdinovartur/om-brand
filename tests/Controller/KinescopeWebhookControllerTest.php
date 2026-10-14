<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Enum\PaymentOfferStatus;
use App\Enum\PracticeSessionStatus;
use App\Tests\Support\PracticePaymentFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class KinescopeWebhookControllerTest extends WebTestCase
{
    use PracticePaymentFixtures;

    /** @return array<string, string> */
    private function kinescopeAuthServer(array $extra = []): array
    {
        return array_merge([
            'CONTENT_TYPE' => 'application/json',
            'PHP_AUTH_USER' => 'test-kinescope',
            'PHP_AUTH_PW' => 'test-kinescope-pass',
        ], $extra);
    }

    public function testLiveFinishedMarksReplayAndDebits(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->createSiteUser($em);
        $inquiry = $this->createInquiry($em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($em, $inquiry, $user, amountKopecks: 555_00);
        $offer->setStatus(PaymentOfferStatus::Paid)->setPaidAt(new \DateTimeImmutable());
        $em->flush();

        $eventId = 'evt-http-'.bin2hex(random_bytes(3));
        $session = $this->createPracticeSession($em, status: PracticeSessionStatus::Live, eventId: $eventId);

        $client->request(
            'POST',
            '/webhooks/kinescope',
            server: $this->kinescopeAuthServer(),
            content: json_encode([
                'event' => 'live.finished',
                'data' => [
                    'event_id' => $eventId,
                    'video_id' => 'video-http-1',
                ],
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok']);

        $em->clear();
        $freshSession = $em->find($session::class, $session->getId());
        $freshOffer = $em->find($offer::class, $offer->getId());
        self::assertSame(PracticeSessionStatus::Replay, $freshSession?->getStatus());
        self::assertSame('video-http-1', $freshSession?->getKinescopeVideoId());
        self::assertNotNull($freshSession?->getDebitedAt());
        self::assertSame(3, $freshOffer?->getSessionsLeft());
    }

    public function testUnknownEventStillOk(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/webhooks/kinescope',
            server: $this->kinescopeAuthServer(),
            content: json_encode([
                'event' => 'live.ping',
                'data' => ['event_id' => 'whatever'],
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok']);
    }

    public function testRejectsBadBasicAuthWhenConfigured(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/webhooks/kinescope',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'PHP_AUTH_USER' => 'wrong',
                'PHP_AUTH_PW' => 'wrong',
            ],
            content: '{"event":"live.finished","data":{"event_id":"no-session"}}',
        );
        self::assertResponseStatusCodeSame(401);
    }
}
