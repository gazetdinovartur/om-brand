<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\PaymentOffer;
use App\Entity\SiteUser;
use App\Enum\PaymentOfferStatus;
use App\Practice\PracticeFormat;
use App\Service\SiteUserTelegramNotifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SiteUserTelegramNotifierTest extends TestCase
{
    public function testSkipsWithoutTelegramIdOrToken(): void
    {
        $notifier = $this->notifier(new MockHttpClient(), botToken: '');
        $user = (new SiteUser())->setDisplayName('X');

        self::assertFalse($notifier->notifyEnrollment($user, PracticeFormat::First));
    }

    public function testSendsEnrollmentPayLinkToUserChat(): void
    {
        $captured = null;
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = [
                'url' => $url,
                'body' => json_decode((string) ($options['body'] ?? ''), true, 512, \JSON_THROW_ON_ERROR),
            ];

            return new MockResponse('{"ok":true}');
        });

        $notifier = $this->notifier($http, botToken: 'bot-token');
        $user = (new SiteUser())->setTelegramId('424242')->setDisplayName('Анна');
        $offer = (new PaymentOffer())
            ->setTitle('Практика разовая')
            ->setAmount(222_00)
            ->setStatus(PaymentOfferStatus::Pending)
            ->setPaymentUrl('https://yookassa.test/pay');

        self::assertTrue($notifier->notifyEnrollment($user, PracticeFormat::Single, $offer));
        self::assertSame('424242', $captured['body']['chat_id']);
        self::assertStringContainsString('https://yookassa.test/pay', $captured['body']['text']);
        self::assertStringContainsString('bot-token', $captured['url']);
    }

    public function testNotifyPaidSendsRoomLink(): void
    {
        $captured = null;
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = json_decode((string) ($options['body'] ?? ''), true, 512, \JSON_THROW_ON_ERROR);

            return new MockResponse('{"ok":true}');
        });

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $route): string => match ($route) {
            'web_practice_room' => 'https://example.test/practice/room',
            default => 'https://example.test/account?tab=practice',
        });

        $notifier = new SiteUserTelegramNotifier(
            httpClient: $http,
            urlGenerator: $urls,
            logger: new NullLogger(),
            botToken: 'bot-token',
        );

        $user = (new SiteUser())->setTelegramId('99')->setDisplayName('Б');
        $offer = (new PaymentOffer())->setTitle('Цикл')->setAmount(555_00)->setStatus(PaymentOfferStatus::Paid);

        self::assertTrue($notifier->notifyPaid($user, $offer));
        self::assertStringContainsString('/practice/room', $captured['text']);
    }

    private function notifier(MockHttpClient $http, string $botToken): SiteUserTelegramNotifier
    {
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://example.test/account?tab=practice');

        return new SiteUserTelegramNotifier(
            httpClient: $http,
            urlGenerator: $urls,
            logger: new NullLogger(),
            botToken: $botToken,
        );
    }
}
