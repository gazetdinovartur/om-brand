<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Inquiry;
use App\Entity\PaymentOffer;
use App\Entity\SiteSettings;
use App\Enum\ContactType;
use App\Enum\InquiryType;
use App\Enum\PaymentOfferStatus;
use App\Service\PracticePaidMailNotifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class PracticePaidMailNotifierTest extends TestCase
{
    public function testIsConfiguredRequiresMailerAndFrom(): void
    {
        $notifier = $this->notifier(mailerDsn: 'null://null', mailFrom: 'a@b.c');
        self::assertFalse($notifier->isConfigured());

        $notifier = $this->notifier(mailerDsn: 'smtp://localhost', mailFrom: '');
        self::assertFalse($notifier->isConfigured());

        $notifier = $this->notifier(mailerDsn: 'smtp://localhost', mailFrom: 'noreply@example.test');
        self::assertTrue($notifier->isConfigured());
    }

    public function testSendRoomAccessSkipsWithoutRecipient(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $notifier = $this->notifier(
            mailer: $mailer,
            mailerDsn: 'smtp://localhost',
            mailFrom: 'noreply@example.test',
        );

        $offer = (new PaymentOffer())
            ->setTitle('Практика')
            ->setAmount(222_00)
            ->setStatus(PaymentOfferStatus::Paid)
            ->setInquiry((new Inquiry())
                ->setName('X')
                ->setContact('not-an-email')
                ->setContactType(ContactType::Telegram)
                ->setInquiryType(InquiryType::Practice)
                ->setMessage('m'));

        self::assertFalse($notifier->sendRoomAccess($offer, new SiteSettings()));
    }

    public function testSendRoomAccessSendsHtmlAndText(): void
    {
        $sent = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (Email $email) use (&$sent): bool {
                $sent = $email;

                return true;
            }));

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(static fn (string $tpl): string => 'body:'.$tpl);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static function (string $route): string {
            return match ($route) {
                'web_practice_room' => 'https://example.test/practice/room',
                'web_library_login' => 'https://example.test/library/login',
                'web_account' => 'https://example.test/account?tab=practice',
                default => 'https://example.test/'.$route,
            };
        });

        $notifier = new PracticePaidMailNotifier(
            mailer: $mailer,
            urlGenerator: $urls,
            twig: $twig,
            logger: new NullLogger(),
            mailerDsn: 'smtp://localhost',
            mailFrom: 'noreply@example.test',
            siteUrl: 'https://example.test',
        );

        $offer = (new PaymentOffer())
            ->setTitle('Практика')
            ->setAmount(222_00)
            ->setStatus(PaymentOfferStatus::Paid)
            ->setInquiry((new Inquiry())
                ->setName('Аня')
                ->setContact('anya@example.test')
                ->setContactType(ContactType::Email)
                ->setInquiryType(InquiryType::Practice)
                ->setMessage('m'));

        self::assertTrue($notifier->sendRoomAccess($offer, (new SiteSettings())->setName('Тест')));
        self::assertInstanceOf(Email::class, $sent);
        self::assertSame('anya@example.test', $sent->getTo()[0]->getAddress());
        self::assertStringContainsString('Оплата получена', $sent->getSubject());
        self::assertSame('body:emails/practice_paid.txt.twig', $sent->getTextBody());
        self::assertSame('body:emails/practice_paid.html.twig', $sent->getHtmlBody());
    }

    private function notifier(
        ?MailerInterface $mailer = null,
        string $mailerDsn = 'smtp://localhost',
        string $mailFrom = 'noreply@example.test',
    ): PracticePaidMailNotifier {
        return new PracticePaidMailNotifier(
            mailer: $mailer ?? $this->createMock(MailerInterface::class),
            urlGenerator: $this->createMock(UrlGeneratorInterface::class),
            twig: $this->createMock(Environment::class),
            logger: new NullLogger(),
            mailerDsn: $mailerDsn,
            mailFrom: $mailFrom,
            siteUrl: 'https://example.test',
        );
    }
}
