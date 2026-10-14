<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Enum\PaymentOfferStatus;
use App\Tests\Support\PracticePaymentFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PaymentControllerTest extends WebTestCase
{
    use PracticePaymentFixtures;

    public function testOplataRedirectsToGatewayUrl(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->createSiteUser($em);
        $inquiry = $this->createInquiry($em, (string) $user->getEmail());
        $offer = $this->createPendingOffer(
            $em,
            $inquiry,
            $user,
            paymentUrl: 'https://yookassa.test/gateway/abc',
        );

        $client->request('GET', '/oplata/'.$offer->getToken());

        self::assertResponseRedirects('https://yookassa.test/gateway/abc');
    }

    public function testOplataRedirectsPaidOfferToAccount(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->createSiteUser($em);
        $inquiry = $this->createInquiry($em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($em, $inquiry, $user);
        $offer->setStatus(PaymentOfferStatus::Paid)->setPaidAt(new \DateTimeImmutable());
        $em->flush();

        $client->request('GET', '/oplata/'.$offer->getToken());

        self::assertResponseRedirects();
        self::assertStringContainsString('/account', (string) $client->getResponse()->headers->get('Location'));
        self::assertStringContainsString('tab=practice', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testOplataReturns404WhenGatewayUrlMissing(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->createSiteUser($em);
        $inquiry = $this->createInquiry($em, (string) $user->getEmail());
        $offer = $this->createPendingOffer($em, $inquiry, $user, paymentUrl: null);
        $offer->setPaymentUrl(null);
        $em->flush();

        $client->request('GET', '/oplata/'.$offer->getToken());

        self::assertResponseStatusCodeSame(404);
    }

    public function testOplataReturns404ForUnknownToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/oplata/00000000-0000-0000-0000-000000000000');

        self::assertResponseStatusCodeSame(404);
    }
}
