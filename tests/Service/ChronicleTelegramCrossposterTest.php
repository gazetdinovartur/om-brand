<?php

namespace App\Tests\Service;

use App\Entity\ChronicleEntry;
use App\Enum\ChronicleStatus;
use App\Repository\ChronicleEntryRepository;
use App\Service\ChronicleTelegramCrossposter;
use App\Service\ChronicleTelegramMessageBuilder;
use App\Service\ChronicleVkMessageBuilder;
use App\Service\TelegramChannelCredentials;
use App\Twig\UploadPathExtension;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ChronicleTelegramCrossposterTest extends TestCase
{
    public function testPostsToChannel(): void
    {
        $entry = new ChronicleEntry();
        $entry->setTitle('Hello TG');
        $entry->setSlug('hello-tg');
        $entry->setShortHash('abcd1234');
        $entry->setStatus(ChronicleStatus::Published);
        $entry->setPublishedAt(new \DateTimeImmutable('-1 hour'));
        $entry->setTgCrosspostRequested(true);

        $http = new MockHttpClient(static function (string $method, string $url): MockResponse {
            if (str_contains($url, 'sendMessage')) {
                return new MockResponse(json_encode([
                    'ok' => true,
                    'result' => ['message_id' => 77],
                ], \JSON_THROW_ON_ERROR));
            }

            self::fail('Unexpected: '.$url);
        });

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('flush');

        $result = $this->crossposter($http, $em)->crosspostEntry($entry);

        self::assertSame('posted', $result);
        self::assertSame(77, $entry->getTgMessageId());
        self::assertNull($entry->getTgCrosspostError());
    }

    public function testSkipsWhenNotRequested(): void
    {
        $entry = new ChronicleEntry();
        $entry->setTitle('T');
        $entry->setSlug('t');
        $entry->setShortHash('abcd1234');
        $entry->setStatus(ChronicleStatus::Published);
        $entry->setPublishedAt(new \DateTimeImmutable('-1 hour'));

        $http = new MockHttpClient(static function () {
            self::fail('HTTP must not be called');
        });

        self::assertSame('skipped', $this->crossposter($http)->crosspostEntry($entry));
    }

    private function crossposter(
        MockHttpClient $http,
        ?EntityManagerInterface $em = null,
    ): ChronicleTelegramCrossposter {
        $creds = new TelegramChannelCredentials(
            botTokenEnv: 'bot-token',
            channelIdEnv: '@testchannel',
            enabledEnv: '1',
        );

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/p/abcd1234');

        $vkBuilder = new ChronicleVkMessageBuilder(
            new UploadPathExtension(sys_get_temp_dir()),
            $urls,
            sys_get_temp_dir(),
            'https://arturlun.ru',
        );

        return new ChronicleTelegramCrossposter(
            $em ?? $this->createMock(EntityManagerInterface::class),
            $this->createMock(ChronicleEntryRepository::class),
            $creds,
            new ChronicleTelegramMessageBuilder($vkBuilder),
            $http,
            new NullLogger(),
        );
    }
}
