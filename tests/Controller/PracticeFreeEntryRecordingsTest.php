<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Enum\PaymentOfferStatus;
use App\Enum\PracticeSessionStatus;
use App\Repository\PaymentOfferRepository;
use App\Tests\Support\PracticePaymentFixtures;
use App\Tests\Support\SiteAuthFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PracticeFreeEntryRecordingsTest extends WebTestCase
{
    use PracticePaymentFixtures;
    use SiteAuthFixtures;

    public function testFirstMeetingDoesNotUnlockRecordingsGallery(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var PaymentOfferRepository $offers */
        $offers = static::getContainer()->get(PaymentOfferRepository::class);

        $em->createQuery('DELETE FROM App\Entity\PracticeSession s')->execute();
        $em->clear();

        $email = 'first-rec-'.bin2hex(random_bytes(3)).'@example.test';
        $user = $this->loginViaEmailCode($client, $em, $email, '/account?tab=practice');
        $inquiry = $this->createInquiry($em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($em, $inquiry, $user, amountKopecks: 0);
        $offer->setStatus(PaymentOfferStatus::Paid)->setPaidAt(new \DateTimeImmutable());
        $em->flush();

        $this->createPracticeSession(
            $em,
            title: 'Секретная запись',
            status: PracticeSessionStatus::Replay,
            eventId: 'first-evt-'.bin2hex(random_bytes(2)),
            videoId: 'first-vid',
            startsAt: new \DateTimeImmutable('-2 days'),
        );

        self::assertTrue($offers->userHasPaidPracticeAccess($user));
        self::assertFalse($offers->userHasPracticeRecordingsAccess($user));

        $client->request('GET', '/account?tab=practice');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#library-practice-recordings-title');
        self::assertStringNotContainsString('Секретная запись', (string) $client->getResponse()->getContent());
    }
}
