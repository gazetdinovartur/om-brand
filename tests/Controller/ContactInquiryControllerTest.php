<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Inquiry;
use App\Enum\ContactType;
use App\Enum\InquiryType;
use App\Tests\Support\SiteAuthFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ContactInquiryControllerTest extends WebTestCase
{
    use SiteAuthFixtures;

    public function testContactPageRendersForm(): void
    {
        $client = static::createClient();
        $client->request('GET', '/contact');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
        self::assertSelectorExists('input[name$="[name]"]');
        self::assertSelectorExists('input[name$="[contact]"]');
    }

    public function testContactJsonSubmissionCreatesInquiry(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/contact');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form')->form();
        $formName = $form->getName();
        $email = 'contact-'.bin2hex(random_bytes(3)).'@example.test';

        $client->request(
            'POST',
            '/contact',
            [
                $formName => [
                    'name' => 'Мария',
                    'contactType' => ContactType::Email->value,
                    'contact' => $email,
                    'inquiryType' => InquiryType::Question->value,
                    'message' => 'Нужна консультация по бренду',
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
        self::assertNotSame('', $payload['message'] ?? '');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $inquiry = $em->getRepository(Inquiry::class)->findOneBy(['contact' => $email], ['id' => 'DESC']);
        self::assertInstanceOf(Inquiry::class, $inquiry);
        self::assertSame('Мария', $inquiry->getName());
        self::assertSame(InquiryType::Question, $inquiry->getInquiryType());
        self::assertSame(ContactType::Email, $inquiry->getContactType());
    }

    public function testContactJsonRejectsMissingConsent(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/contact');
        $form = $crawler->filter('form')->form();
        $formName = $form->getName();

        $client->request(
            'POST',
            '/contact',
            [
                $formName => [
                    'name' => 'Без согласия',
                    'contactType' => ContactType::Telegram->value,
                    'contact' => '@no_consent_'.bin2hex(random_bytes(2)),
                    'inquiryType' => InquiryType::Question->value,
                    'message' => 'текст',
                    'website' => '',
                    '_token' => $form[$formName]['_token']->getValue(),
                ],
            ],
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                'REMOTE_ADDR' => $this->uniqueClientIp(),
            ],
        );

        self::assertResponseStatusCodeSame(422);
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertFalse($payload['ok']);
    }

    public function testContactHoneypotIsSilentlyAcceptedWithoutPersisting(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/contact');
        $form = $crawler->filter('form')->form();
        $formName = $form->getName();
        $contact = 'bot-'.bin2hex(random_bytes(3)).'@example.test';

        $client->request(
            'POST',
            '/contact',
            [
                $formName => [
                    'name' => 'Bot',
                    'contactType' => ContactType::Email->value,
                    'contact' => $contact,
                    'inquiryType' => InquiryType::Question->value,
                    'message' => 'spam',
                    'website' => 'http://spam.test',
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
        self::assertNull($em->getRepository(Inquiry::class)->findOneBy(['contact' => $contact]));
    }
}
