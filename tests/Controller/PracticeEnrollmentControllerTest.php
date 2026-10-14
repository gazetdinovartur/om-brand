<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Inquiry;
use App\Entity\PaymentOffer;
use App\Enum\InquiryType;
use App\Enum\PaymentOfferStatus;
use App\Infrastructure\Yookassa\YookassaClient;
use App\Practice\PracticeFormat;
use App\Tests\Support\PracticePaymentFixtures;
use App\Tests\Support\SiteAuthFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PracticeEnrollmentControllerTest extends WebTestCase
{
    use PracticePaymentFixtures;
    use SiteAuthFixtures;

    public function testPracticePageRendersEnrollmentForm(): void
    {
        $client = static::createClient();
        $client->request('GET', '/practice');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
        self::assertSelectorExists('input[name$="[email]"]');
        self::assertSelectorExists('select[name$="[format]"], input[name$="[format]"]');
    }

    public function testFreePracticeEnrollmentCreatesPaidOfferAndSiteUser(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->getContainer()->set(YookassaClient::class, $this->mockYookassaClient([]));

        $crawler = $client->request('GET', '/practice');
        $form = $crawler->filter('form')->form();
        $formName = $form->getName();
        $email = 'practice-free-'.bin2hex(random_bytes(3)).'@example.test';

        $client->request(
            'POST',
            '/practice',
            [
                $formName => [
                    'name' => 'Оля',
                    'email' => $email,
                    'format' => PracticeFormat::First->value,
                    'inquiryType' => InquiryType::Practice->value,
                    'message' => 'Готова к первой встрече',
                    'website' => '',
                    'consent' => '1',
                    '_token' => $form[$formName]['_token']->getValue(),
                ],
            ],
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                'REMOTE_ADDR' => $this->uniqueClientIp(),
            ],
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok']);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $inquiry = $em->getRepository(Inquiry::class)->findOneBy(['contact' => $email], ['id' => 'DESC']);
        self::assertInstanceOf(Inquiry::class, $inquiry);
        self::assertSame(InquiryType::Practice, $inquiry->getInquiryType());

        $offer = $em->getRepository(PaymentOffer::class)->findOneBy(['inquiry' => $inquiry], ['id' => 'DESC']);
        self::assertInstanceOf(PaymentOffer::class, $offer);
        self::assertSame(PaymentOfferStatus::Paid, $offer->getStatus());
        self::assertSame(0, $offer->getAmount());
        self::assertNull($offer->getYookassaPaymentId());
        self::assertNotNull($offer->getSiteUser());
        self::assertSame($email, $offer->getSiteUser()?->getEmail());
    }

    public function testPaidPracticeEnrollmentCreatesYookassaPayment(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $paymentId = $this->uniqueYookassaPaymentId('http-enroll');
        $client->getContainer()->set(YookassaClient::class, $this->mockYookassaClient([
            $this->yookassaCreateResponse($paymentId, 'https://yookassa.test/http-enroll'),
        ]));

        $crawler = $client->request('GET', '/practice');
        $form = $crawler->filter('form')->form();
        $formName = $form->getName();
        $email = 'practice-paid-'.bin2hex(random_bytes(3)).'@example.test';

        $client->request(
            'POST',
            '/practice',
            [
                $formName => [
                    'name' => 'Павел',
                    'email' => $email,
                    'format' => PracticeFormat::Single->value,
                    'inquiryType' => InquiryType::Practice->value,
                    'message' => '',
                    'website' => '',
                    'consent' => '1',
                    '_token' => $form[$formName]['_token']->getValue(),
                ],
            ],
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                'REMOTE_ADDR' => $this->uniqueClientIp(),
            ],
        );

        $status = $client->getResponse()->getStatusCode();
        $body = (string) $client->getResponse()->getContent();
        self::assertSame(200, $status, $body);
        $payload = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok'], $body);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $inquiry = $em->getRepository(Inquiry::class)->findOneBy(['contact' => $email], ['id' => 'DESC']);
        $offer = $em->getRepository(PaymentOffer::class)->findOneBy(['inquiry' => $inquiry], ['id' => 'DESC']);

        self::assertInstanceOf(PaymentOffer::class, $offer);
        self::assertSame(PaymentOfferStatus::Pending, $offer->getStatus());
        self::assertSame(222_00, $offer->getAmount());
        self::assertSame($paymentId, $offer->getYookassaPaymentId());
        self::assertSame('https://yookassa.test/http-enroll', $offer->getPaymentUrl());
    }

    public function testPracticeAccountTabShowsOffersAfterLogin(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->getContainer()->set(YookassaClient::class, $this->mockYookassaClient([]));

        $crawler = $client->request('GET', '/practice');
        $form = $crawler->filter('form')->form();
        $formName = $form->getName();
        $email = 'practice-tab-'.bin2hex(random_bytes(3)).'@example.test';

        $client->request(
            'POST',
            '/practice',
            [
                $formName => [
                    'name' => 'Света',
                    'email' => $email,
                    'format' => PracticeFormat::First->value,
                    'inquiryType' => InquiryType::Practice->value,
                    'message' => '',
                    'website' => '',
                    'consent' => '1',
                    '_token' => $form[$formName]['_token']->getValue(),
                ],
            ],
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'REMOTE_ADDR' => $this->uniqueClientIp(),
            ],
        );
        self::assertResponseIsSuccessful();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->loginViaEmailCode($client, $em, email: $email, targetPath: '/account?tab=practice');
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Практика', (string) $client->getResponse()->getContent());
        self::assertSelectorExists('a[href*="practice/room"], .library-account__offer-list, .library-account__practice-cta');
    }

    public function testPracticeJsonRejectsInvalidEmail(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/practice');
        $form = $crawler->filter('form')->form();
        $formName = $form->getName();

        $client->request(
            'POST',
            '/practice',
            [
                $formName => [
                    'name' => 'Без email',
                    'email' => 'bad',
                    'format' => PracticeFormat::First->value,
                    'inquiryType' => InquiryType::Practice->value,
                    'message' => '',
                    'website' => '',
                    'consent' => '1',
                    '_token' => $form[$formName]['_token']->getValue(),
                ],
            ],
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'REMOTE_ADDR' => $this->uniqueClientIp(),
            ],
        );

        self::assertResponseStatusCodeSame(422);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertFalse($payload['ok']);
    }
}
