<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Enum\PaymentOfferStatus;
use App\Infrastructure\Yookassa\YookassaClient;
use App\Tests\Support\PracticePaymentFixtures;
use App\Tests\Support\SiteAuthFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PracticeYookassaReturnHttpTest extends WebTestCase
{
    use PracticePaymentFixtures;
    use SiteAuthFixtures;

    public function testYkReturnMarksOfferPaidAndRedirects(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $paymentId = $this->uniqueYookassaPaymentId('http-ret');
        $container->set(YookassaClient::class, $this->mockYookassaClient([
            $this->yookassaVerifyResponse('succeeded', $paymentId),
        ]));

        $email = 'yk-ret-'.bin2hex(random_bytes(3)).'@example.test';
        $user = $this->loginViaEmailCode($client, $em, $email, '/account?tab=practice');
        $inquiry = $this->createInquiry($em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($em, $inquiry, $user, yookassaPaymentId: $paymentId);

        $client->request('GET', '/account?tab=practice&yk_return=1&token='.$offer->getToken());
        self::assertResponseRedirects('/account?tab=practice');
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        $em->clear();
        $fresh = $em->find($offer::class, $offer->getId());
        self::assertSame(PaymentOfferStatus::Paid, $fresh?->getStatus());
    }

    public function testYkReturnRejectsForeignOffer(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createSiteUser($em);
        $inquiry = $this->createInquiry($em, (string) $owner->getEmail());
        $offer = $this->createPendingOffer($em, $inquiry, $owner);

        $email = 'yk-foreign-'.bin2hex(random_bytes(3)).'@example.test';
        $this->loginViaEmailCode($client, $em, $email, '/account?tab=practice');

        $client->request('GET', '/account?tab=practice&yk_return=1&token='.$offer->getToken());
        self::assertResponseRedirects('/account?tab=practice');

        $em->clear();
        $fresh = $em->find($offer::class, $offer->getId());
        self::assertSame(PaymentOfferStatus::Pending, $fresh?->getStatus());
    }
}
