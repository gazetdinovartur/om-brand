<?php

namespace App\Tests\Service;

use App\Entity\ChronicleEntry;
use App\Enum\ChronicleStatus;
use App\Repository\ChronicleEntryRepository;
use App\Service\ChronicleVkCrossposter;
use App\Service\ChronicleVkMessageBuilder;
use App\Service\VkApiClient;
use App\Service\VkCredentials;
use App\Twig\UploadPathExtension;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ChronicleVkCrossposterTest extends TestCase
{
    public function testSkipsWhenAlreadyPosted(): void
    {
        $entry = new ChronicleEntry();
        $entry->setTitle('T');
        $entry->setSlug('t');
        $entry->setShortHash('abcd1234');
        $entry->setStatus(ChronicleStatus::Published);
        $entry->setPublishedAt(new \DateTimeImmutable('-1 hour'));
        $entry->setVkPostId(42);

        $http = new MockHttpClient(static function () {
            self::fail('VK HTTP must not be called for already posted entry');
        });

        self::assertSame('skipped', $this->crossposter($http)->crosspostEntry($entry));
    }

    public function testSkipsImportedFromVk(): void
    {
        $entry = new ChronicleEntry();
        $entry->setTitle('T');
        $entry->setSlug('t');
        $entry->setShortHash('abcd1234');
        $entry->setStatus(ChronicleStatus::Published);
        $entry->setPublishedAt(new \DateTimeImmutable('-1 hour'));
        $entry->setSourceKey('vk:wall:99');

        $http = new MockHttpClient(static function () {
            self::fail('VK HTTP must not be called for imported VK entry');
        });

        self::assertSame('skipped', $this->crossposter($http)->crosspostEntry($entry));
    }

    private function crossposter(MockHttpClient $http): ChronicleVkCrossposter
    {
        $creds = $this->enabledCredentials();
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/p/abcd1234');

        return new ChronicleVkCrossposter(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(ChronicleEntryRepository::class),
            $creds,
            new VkApiClient($http, $creds),
            new ChronicleVkMessageBuilder(
                new UploadPathExtension(sys_get_temp_dir()),
                $urls,
                sys_get_temp_dir(),
                'https://arturlun.ru',
            ),
            new NullLogger(),
        );
    }

    private function enabledCredentials(): VkCredentials
    {
        $dir = sys_get_temp_dir().'/vk-cred-'.bin2hex(random_bytes(4));
        mkdir($dir.'/vk', 0700, true);
        file_put_contents($dir.'/vk/user_access_token', "token\n");

        return new VkCredentials(
            projectDir: $dir,
            userTokenEnv: 'token-from-env',
            enabledEnv: '1',
        );
    }
}
