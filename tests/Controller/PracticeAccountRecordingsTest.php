<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Enum\PaymentOfferStatus;
use App\Enum\PracticeSessionStatus;
use App\Tests\Support\PracticePaymentFixtures;
use App\Tests\Support\SiteAuthFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PracticeAccountRecordingsTest extends WebTestCase
{
    use PracticePaymentFixtures;
    use SiteAuthFixtures;

    public function testAccountShowsRemainingAndRecordings(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $em->createQuery('DELETE FROM App\Entity\PracticeSession s')->execute();
        $em->clear();

        $email = 'cab-rec-'.bin2hex(random_bytes(3)).'@example.test';
        $user = $this->loginViaEmailCode($client, $em, $email, '/account?tab=practice');
        $inquiry = $this->createInquiry($em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($em, $inquiry, $user, amountKopecks: 555_00);
        $offer->setStatus(PaymentOfferStatus::Paid)->setPaidAt(new \DateTimeImmutable());
        $em->flush();

        $this->createPracticeSession(
            $em,
            title: 'Запись для кабинета',
            status: PracticeSessionStatus::Replay,
            eventId: 'cab-evt-'.bin2hex(random_bytes(2)),
            videoId: 'cab-vid',
            startsAt: new \DateTimeImmutable('-2 days'),
        );

        $client->request('GET', '/account?tab=practice');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.library-account__practice-balance', 'Осталось встреч: 4 из 4');
        self::assertSelectorTextContains('.library-account__offer-meta', 'осталось 4 из 4');
        self::assertSelectorTextContains('#library-practice-recordings-title', 'Галерея записей');
        self::assertSelectorTextContains('.library-account__recordings', 'Запись для кабинета');
    }
}
