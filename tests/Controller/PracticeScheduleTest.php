<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\PracticeSession;
use App\Enum\PracticeSessionKind;
use App\Enum\PracticeSessionStatus;
use App\Tests\Support\PracticePaymentFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PracticeScheduleTest extends WebTestCase
{
    use PracticePaymentFixtures;

    public function testPracticePageShowsUpcomingSessionsFromAdmin(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $this->createPracticeSession(
            $em,
            title: 'Утренняя регуляция',
            status: PracticeSessionStatus::Scheduled,
            startsAt: new \DateTimeImmutable('+3 days'),
            kind: PracticeSessionKind::Speak,
            roomId: 'room-sched-1',
            roomCode: 'sched-code-1',
        );
        $this->createPracticeSession(
            $em,
            title: 'Эфир сейчас',
            status: PracticeSessionStatus::Live,
            startsAt: new \DateTimeImmutable('-10 minutes'),
            kind: PracticeSessionKind::Speak,
            roomId: 'room-live-1',
            roomCode: 'live-code-1',
        );
        $this->createPracticeSession(
            $em,
            title: 'Старая запись',
            status: PracticeSessionStatus::Completed,
            startsAt: new \DateTimeImmutable('-5 days'),
            kind: PracticeSessionKind::Speak,
        );

        $crawler = $client->request('GET', '/practice');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.practice-schedule');
        self::assertSelectorTextContains('.practice-schedule', 'Эфир сейчас');
        self::assertSelectorTextContains('.practice-schedule', 'Утренняя регуляция');
        self::assertSelectorTextContains('.practice-schedule__badge', 'сейчас');
        self::assertStringNotContainsString('Старая запись', $crawler->filter('.practice-schedule')->text());
    }

    public function testPracticePageShowsFallbackWhenNoUpcomingSessions(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Shared test DB may keep scheduled/live from other tests — archive them.
        foreach ($em->getRepository(PracticeSession::class)->findAll() as $session) {
            if (\in_array($session->getStatus(), [PracticeSessionStatus::Scheduled, PracticeSessionStatus::Live], true)) {
                $session->setStatus(PracticeSessionStatus::Completed);
            }
        }
        $em->flush();

        $client->request('GET', '/practice');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.practice-schedule');
        self::assertSelectorTextContains('.practice-steps__block', 'Ближайшие встречи появятся здесь');
    }
}
