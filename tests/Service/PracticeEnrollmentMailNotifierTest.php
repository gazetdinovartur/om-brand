<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Inquiry;
use App\Entity\PaymentOffer;
use App\Entity\SiteSettings;
use App\Enum\ContactType;
use App\Enum\InquiryType;
use App\Enum\PaymentOfferStatus;
use App\Practice\PracticeFormat;
use App\Service\PracticeEnrollmentMailNotifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class PracticeEnrollmentMailNotifierTest extends TestCase
{
    public function testPaidEnrollmentMailUsesDirectPaymentUrl(): void
    {
        $seenPaymentUrl = null;
        $sent = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (Email $email) use (&$sent): bool {
                $sent = $email;

                return true;
            }));

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(static function (string $tpl, array $ctx) use (&$seenPaymentUrl): string {
            $seenPaymentUrl = $ctx['paymentUrl'] ?? null;

            return 'mail:'.$tpl;
        });

        $notifier = new PracticeEnrollmentMailNotifier(
            mailer: $mailer,
            urlGenerator: $this->urlGenerator(),
            twig: $twig,
            logger: new NullLogger(),
            mailerDsn: 'smtp://localhost',
            mailFrom: 'noreply@example.test',
            siteUrl: 'https://example.test',
        );

        $offer = (new PaymentOffer())
            ->setTitle('Регулярная практика — разовая встреча')
            ->setAmount(222_00)
            ->setStatus(PaymentOfferStatus::Pending)
            ->setPaymentUrl('https://yookassa.test/pay-direct');

        $inquiry = (new Inquiry())
            ->setName('Аня')
            ->setContact('anya@example.test')
            ->setContactType(ContactType::Email)
            ->setInquiryType(InquiryType::Practice)
            ->setMessage('m');

        self::assertTrue($notifier->send(
            $inquiry,
            PracticeFormat::Single,
            'anya@example.test',
            (new SiteSettings())->setName('Тест'),
            $offer,
        ));
        self::assertSame('https://yookassa.test/pay-direct', $seenPaymentUrl);
        self::assertInstanceOf(Email::class, $sent);
        self::assertStringContainsString('Ссылка на оплату', $sent->getSubject());
    }

    public function testFreeEnrollmentMailHasNoPaymentUrl(): void
    {
        $seenPaymentUrl = 'unset';
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::exactly(2))
            ->method('render')
            ->willReturnCallback(static function (string $tpl, array $ctx) use (&$seenPaymentUrl): string {
                $seenPaymentUrl = $ctx['paymentUrl'] ?? null;

                return 'mail:'.$tpl;
            });

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send');

        $notifier = new PracticeEnrollmentMailNotifier(
            mailer: $mailer,
            urlGenerator: $this->urlGenerator(),
            twig: $twig,
            logger: new NullLogger(),
            mailerDsn: 'smtp://localhost',
            mailFrom: 'noreply@example.test',
            siteUrl: 'https://example.test',
        );

        $inquiry = (new Inquiry())
            ->setName('Борис')
            ->setContact('boris@example.test')
            ->setContactType(ContactType::Email)
            ->setInquiryType(InquiryType::Practice)
            ->setMessage('m');

        self::assertTrue($notifier->send(
            $inquiry,
            PracticeFormat::First,
            'boris@example.test',
            new SiteSettings(),
            null,
        ));
        self::assertNull($seenPaymentUrl);
    }

    private function urlGenerator(): UrlGeneratorInterface
    {
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://example.test/account?tab=practice');

        return $urls;
    }
}
