<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Inquiry;
use App\Entity\PaymentOffer;
use App\Entity\SiteUser;
use App\Enum\InquiryType;
use App\Enum\PaymentOfferStatus;
use App\Infrastructure\Yookassa\YookassaClient;
use App\Practice\PracticeFormat;
use App\Tests\Support\PracticePaymentFixtures;
use App\Tests\Support\SiteAuthFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
final class PracticeAuthEnrollmentTest extends WebTestCase
{
    use PracticePaymentFixtures;
    use SiteAuthFixtures;

    public function testAuthenticatedFormHidesConsentAndEmailWhenAccountHasEmail(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $email = 'auth-form-'.bin2hex(random_bytes(3)).'@example.test';
        $this->loginViaEmailCode($client, $em, email: $email, targetPath: '/practice');
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name$="[name]"]');
        self::assertSelectorNotExists('input[name$="[consent]"]');
        self::assertSelectorNotExists('input[name$="[email]"]');
        self::assertStringContainsString($email, (string) $client->getResponse()->getContent());
    }

    public function testAuthenticatedEnrollmentUsesAccountEmailWithoutConsent(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->getContainer()->set(YookassaClient::class, $this->mockYookassaClient([]));

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $email = 'auth-enroll-'.bin2hex(random_bytes(3)).'@example.test';
        $this->loginViaEmailCode($client, $em, email: $email, targetPath: '/practice');
        $crawler = $client->followRedirect();

        $form = $crawler->filter('form')->form();
        $formName = $form->getName();

        $client->request(
            'POST',
            '/practice',
            [
                $formName => [
                    'name' => 'Обновлённое имя',
                    'format' => PracticeFormat::First->value,
                    'inquiryType' => InquiryType::Practice->value,
                    'message' => '',
                    'website' => '',
                    '_token' => $form[$formName]['_token']->getValue(),
                ],
            ],
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'REMOTE_ADDR' => $this->uniqueClientIp(),
            ],
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok']);
        self::assertStringContainsString('Своём месте', $payload['message']);

        $user = $em->getRepository(SiteUser::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(SiteUser::class, $user);
        self::assertSame('Обновлённое имя', $user->getDisplayName());

        $offer = $em->getRepository(PaymentOffer::class)->findOneBy(['siteUser' => $user], ['id' => 'DESC']);
        self::assertInstanceOf(PaymentOffer::class, $offer);
        self::assertSame(PaymentOfferStatus::Paid, $offer->getStatus());
    }

    public function testAuthenticatedWithoutEmailRequiresEmailForPaidFormat(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new SiteUser())
            ->setTelegramId((string) random_int(1000000, 9999999))
            ->setTelegramUsername('tg_practice_'.bin2hex(random_bytes(2)))
            ->setDisplayName('TG User');
        $em->persist($user);
        $em->flush();

        // Login via email challenge is unavailable without email — create session by temporary email then strip it.
        $tempEmail = 'tmp-'.bin2hex(random_bytes(3)).'@example.test';
        $user->setEmail($tempEmail);
        $em->flush();
        $this->loginViaEmailCode($client, $em, email: $tempEmail, targetPath: '/practice');
        $user->setEmail(null);
        $em->flush();
        $crawler = $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name$="[email]"]');
        self::assertSelectorNotExists('input[name$="[consent]"]');

        $form = $crawler->filter('form')->form();
        $formName = $form->getName();

        $client->request(
            'POST',
            '/practice',
            [
                $formName => [
                    'name' => 'TG User',
                    'email' => '',
                    'format' => PracticeFormat::Single->value,
                    'inquiryType' => InquiryType::Practice->value,
                    'message' => '',
                    'website' => '',
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

    public function testAuthenticatedWithoutEmailCanSaveEmailOnPaidEnroll(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $paymentId = $this->uniqueYookassaPaymentId('auth-paid');
        $client->getContainer()->set(YookassaClient::class, $this->mockYookassaClient([
            $this->yookassaCreateResponse($paymentId, 'https://yookassa.test/auth-paid'),
        ]));

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $tempEmail = 'tmp2-'.bin2hex(random_bytes(3)).'@example.test';
        $finalEmail = 'final-'.bin2hex(random_bytes(3)).'@example.test';
        $user = (new SiteUser())->setEmail($tempEmail)->setDisplayName('Плательщик');
        $em->persist($user);
        $em->flush();

        $this->loginViaEmailCode($client, $em, email: $tempEmail, targetPath: '/practice');
        $user->setEmail(null);
        $em->flush();
        $crawler = $client->followRedirect();

        $form = $crawler->filter('form')->form();
        $formName = $form->getName();

        $client->request(
            'POST',
            '/practice',
            [
                $formName => [
                    'name' => 'Плательщик',
                    'email' => $finalEmail,
                    'format' => PracticeFormat::Single->value,
                    'inquiryType' => InquiryType::Practice->value,
                    'message' => '',
                    'website' => '',
                    '_token' => $form[$formName]['_token']->getValue(),
                ],
            ],
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'REMOTE_ADDR' => $this->uniqueClientIp(),
            ],
        );

        self::assertResponseIsSuccessful();
        $em->clear();
        $freshUser = $em->getRepository(SiteUser::class)->findOneBy(['email' => $finalEmail]);
        self::assertInstanceOf(SiteUser::class, $freshUser);
        $offer = $em->getRepository(PaymentOffer::class)->findOneBy(['siteUser' => $freshUser], ['id' => 'DESC']);
        self::assertSame($paymentId, $offer?->getYookassaPaymentId());
    }

    public function testCancelPendingOfferFromAccount(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $email = 'cancel-'.bin2hex(random_bytes(3)).'@example.test';
        $user = $this->loginViaEmailCode($client, $em, email: $email, targetPath: '/account?tab=practice');
        $inquiry = $this->createInquiry($em, $email);
        $offer = $this->createPendingOffer($em, $inquiry, $user);
        $crawler = $client->followRedirect();

        $token = $offer->getToken();
        $csrfInput = $crawler->filter(sprintf('form.library-account__offer-form input[value="%s"]', $token))
            ->closest('form')
            ->filter('input[name="_token"]');
        self::assertGreaterThan(0, $csrfInput->count());

        $client->request('POST', '/account?tab=practice', [
            'action' => 'cancel_offer',
            'offer_token' => $token,
            '_token' => $csrfInput->attr('value'),
        ], server: ['REMOTE_ADDR' => $this->uniqueClientIp()]);

        self::assertResponseRedirects('/account?tab=practice');
        $em->clear();
        $fresh = $em->getRepository(PaymentOffer::class)->findOneBy(['token' => $token]);
        self::assertSame(PaymentOfferStatus::Cancelled, $fresh?->getStatus());
    }

    public function testDeleteCancelledOfferUnlinksFromUser(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $email = 'delete-offer-'.bin2hex(random_bytes(3)).'@example.test';
        $user = $this->loginViaEmailCode($client, $em, email: $email, targetPath: '/account?tab=practice');
        $inquiry = $this->createInquiry($em, $email);
        $offer = $this->createPendingOffer($em, $inquiry, $user);
        $offer->setStatus(PaymentOfferStatus::Cancelled);
        $em->flush();
        $token = $offer->getToken();
        $crawler = $client->followRedirect();

        $csrfInput = $crawler->filter(sprintf('form.library-account__offer-form input[value="%s"]', $token))
            ->closest('form')
            ->filter('input[name="_token"]');
        self::assertGreaterThan(0, $csrfInput->count());

        $client->request('POST', '/account?tab=practice', [
            'action' => 'delete_offer',
            'offer_token' => $token,
            '_token' => $csrfInput->attr('value'),
        ], server: ['REMOTE_ADDR' => $this->uniqueClientIp()]);

        self::assertResponseRedirects('/account?tab=practice');
        $em->clear();
        $fresh = $em->getRepository(PaymentOffer::class)->findOneBy(['token' => $token]);
        self::assertNotNull($fresh);
        self::assertNull($fresh->getSiteUser());
        self::assertInstanceOf(Inquiry::class, $fresh->getInquiry());
    }

    public function testCannotCancelForeignOffer(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createSiteUser($em);
        $inquiry = $this->createInquiry($em, (string) $owner->getEmail());
        $offer = $this->createPendingOffer($em, $inquiry, $owner);

        $this->loginViaEmailCode(
            $client,
            $em,
            email: 'intruder-offer-'.bin2hex(random_bytes(3)).'@example.test',
            targetPath: '/account?tab=practice',
        );
        $client->followRedirect();

        $client->request('POST', '/account?tab=practice', [
            'action' => 'cancel_offer',
            'offer_token' => $offer->getToken(),
            '_token' => 'invalid',
        ], server: ['REMOTE_ADDR' => $this->uniqueClientIp()]);

        self::assertContains($client->getResponse()->getStatusCode(), [403, 404]);
        $em->clear();
        $fresh = $em->getRepository(PaymentOffer::class)->findOneBy(['token' => $offer->getToken()]);
        self::assertSame(PaymentOfferStatus::Pending, $fresh?->getStatus());
    }
}
