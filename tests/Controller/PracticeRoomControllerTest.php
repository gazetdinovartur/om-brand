<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Enum\PaymentOfferStatus;
use App\Enum\PracticeSessionKind;
use App\Enum\PracticeSessionStatus;
use App\Tests\Support\PracticePaymentFixtures;
use App\Tests\Support\SiteAuthFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PracticeRoomControllerTest extends WebTestCase
{
    use PracticePaymentFixtures;
    use SiteAuthFixtures;

    private function clearPracticeSessions(EntityManagerInterface $em): void
    {
        $em->createQuery('DELETE FROM App\Entity\PracticeSession s')->execute();
        $em->clear();
    }

    public function testRoomShowsEmbedWhenLive(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->clearPracticeSessions($em);

        $email = 'room-live-'.bin2hex(random_bytes(3)).'@example.test';
        $user = $this->loginViaEmailCode($client, $em, $email, '/practice/room');
        $inquiry = $this->createInquiry($em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($em, $inquiry, $user);
        $offer->setStatus(PaymentOfferStatus::Paid)->setPaidAt(new \DateTimeImmutable());
        $em->flush();

        $this->createPracticeSession(
            $em,
            title: 'Эфир тела',
            status: PracticeSessionStatus::Live,
            eventId: 'live-evt-'.bin2hex(random_bytes(2)),
        );

        $client->request('GET', '/practice/room');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.practice-room__session-title', 'Эфир тела');
        self::assertSelectorExists('iframe[src*="kinescope.io/embed/"]');
    }

    public function testRoomShowsWaitingWhenNoLiveOrReplay(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->clearPracticeSessions($em);

        $email = 'room-wait-'.bin2hex(random_bytes(3)).'@example.test';
        $user = $this->loginViaEmailCode($client, $em, $email, '/practice/room');
        $inquiry = $this->createInquiry($em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($em, $inquiry, $user);
        $offer->setStatus(PaymentOfferStatus::Paid)->setPaidAt(new \DateTimeImmutable());
        $em->flush();

        $this->createPracticeSession(
            $em,
            title: 'Через неделю',
            status: PracticeSessionStatus::Scheduled,
            startsAt: new \DateTimeImmutable('+7 days'),
        );

        $client->request('GET', '/practice/room');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.practice-room__status', 'Сейчас эфира нет');
        self::assertSelectorTextContains('.practice-room__hint', 'Через неделю');
    }

    public function testRoomBlockedWhenSessionsExhaustedAndNoReplay(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->clearPracticeSessions($em);

        $email = 'room-out-'.bin2hex(random_bytes(3)).'@example.test';
        $user = $this->loginViaEmailCode($client, $em, $email, '/account?tab=practice');
        $inquiry = $this->createInquiry($em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($em, $inquiry, $user);
        $offer
            ->setStatus(PaymentOfferStatus::Paid)
            ->setPaidAt(new \DateTimeImmutable())
            ->setSessionsLeft(0);
        $em->flush();

        $client->request('GET', '/practice/room');
        self::assertResponseRedirects('/account?tab=practice');
    }

    public function testRoomAllowsReplayWhenLeftIsZero(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->clearPracticeSessions($em);

        $email = 'room-replay-'.bin2hex(random_bytes(3)).'@example.test';
        $user = $this->loginViaEmailCode($client, $em, $email, '/practice/room');
        $inquiry = $this->createInquiry($em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($em, $inquiry, $user);
        $offer
            ->setStatus(PaymentOfferStatus::Paid)
            ->setPaidAt(new \DateTimeImmutable())
            ->setSessionsLeft(0);
        $em->flush();

        $this->createPracticeSession(
            $em,
            title: 'Запись прошлой',
            status: PracticeSessionStatus::Replay,
            eventId: 'replay-evt-'.bin2hex(random_bytes(2)),
            videoId: 'replay-vid',
            startsAt: new \DateTimeImmutable('-1 day'),
        );

        $client->request('GET', '/practice/room');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.practice-room__session-title', 'Запись прошлой');
        self::assertSelectorExists('iframe[src*="kinescope.io/embed/replay-vid"]');
    }

    public function testRoomShowsSpeakIframeWhenLiveSpeak(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->clearPracticeSessions($em);

        $email = 'room-speak-'.bin2hex(random_bytes(3)).'@example.test';
        $user = $this->loginViaEmailCode($client, $em, $email, '/practice/room');
        $inquiry = $this->createInquiry($em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($em, $inquiry, $user);
        $offer->setStatus(PaymentOfferStatus::Paid)->setPaidAt(new \DateTimeImmutable());
        $em->flush();

        $code = 'speak-'.bin2hex(random_bytes(2));
        $this->createPracticeSession(
            $em,
            title: 'Speak эфир',
            status: PracticeSessionStatus::Live,
            kind: PracticeSessionKind::Speak,
            roomId: 'room-'.bin2hex(random_bytes(2)),
            roomCode: $code,
        );

        $client->request('GET', '/practice/room');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.practice-room__session-title', 'Speak эфир');
        self::assertSelectorExists('iframe[src="https://speak.kinescope.io/'.$code.'"]');
    }
}
