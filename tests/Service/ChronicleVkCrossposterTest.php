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
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ChronicleVkCrossposterTest extends TestCase
{
    public function testSkipsWhenAlreadyPosted(): void
    {
        $entry = $this->publishedEntry();
        $entry->setVkPostId(42);

        $http = new MockHttpClient(static function () {
            self::fail('VK HTTP must not be called for already posted entry');
        });

        self::assertSame('skipped', $this->crossposter($http)->crosspostEntry($entry));
    }

    public function testSkipsImportedFromVk(): void
    {
        $entry = $this->publishedEntry();
        $entry->setSourceKey('vk:wall:99');

        $http = new MockHttpClient(static function () {
            self::fail('VK HTTP must not be called for imported VK entry');
        });

        self::assertSame('skipped', $this->crossposter($http)->crosspostEntry($entry));
    }

    public function testSkipsWhenCrosspostNotRequested(): void
    {
        $entry = $this->publishedEntry();
        $entry->setVkCrosspostRequested(false);

        $http = new MockHttpClient(static function () {
            self::fail('VK HTTP must not be called when crosspost not requested');
        });

        self::assertSame('skipped', $this->crossposter($http)->crosspostEntry($entry));
    }

    public function testSkipsWhenPendingMarkerSet(): void
    {
        $entry = $this->publishedEntry();
        $entry->setVkCrosspostRequested(true);
        $entry->setVkCrosspostError(VkCredentials::PENDING_MARKER);

        $http = new MockHttpClient(static function () {
            self::fail('VK HTTP must not be called while pending marker is set');
        });

        self::assertSame('skipped', $this->crossposter($http)->crosspostEntry($entry));
    }

    public function testSkipsDraftEntry(): void
    {
        $entry = $this->publishedEntry();
        $entry->setStatus(ChronicleStatus::Draft);
        $entry->setPublishedAt(null);
        $entry->setVkCrosspostRequested(true);

        $http = new MockHttpClient(static function () {
            self::fail('VK HTTP must not be called for draft entry');
        });

        self::assertSame('skipped', $this->crossposter($http)->crosspostEntry($entry));
    }

    public function testDefersScheduledPostBeyondVkWindow(): void
    {
        $entry = $this->publishedEntry();
        $entry->setStatus(ChronicleStatus::Scheduled);
        $entry->setPublishedAt(new \DateTimeImmutable('+40 days'));
        $entry->setVkCrosspostRequested(true);

        $http = new MockHttpClient(static function () {
            self::fail('VK HTTP must not be called for far-future scheduled entry');
        });

        self::assertSame('deferred', $this->crossposter($http)->crosspostEntry($entry));
    }

    public function testPostsPublishedEntryToVk(): void
    {
        $entry = $this->publishedEntry();
        $entry->setVkCrosspostRequested(true);

        $http = new MockHttpClient(static function (string $method, string $url): MockResponse {
            if (str_contains($url, 'wall.post')) {
                return new MockResponse(json_encode(['response' => ['post_id' => 501]], \JSON_THROW_ON_ERROR));
            }

            self::fail('Unexpected VK HTTP call: '.$url);
        });

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('flush');

        $result = $this->crossposter($http, $em)->crosspostEntry($entry);

        self::assertSame('posted', $result);
        self::assertSame(501, $entry->getVkPostId());
        self::assertNull($entry->getVkCrosspostError());
        self::assertNotNull($entry->getVkPostedAt());
    }

    public function testStoresErrorWhenVkApiFails(): void
    {
        $entry = $this->publishedEntry();
        $entry->setVkCrosspostRequested(true);

        $http = new MockHttpClient(static function (string $method, string $url): MockResponse {
            if (str_contains($url, 'wall.post')) {
                return new MockResponse(json_encode([
                    'error' => ['error_code' => 15, 'error_msg' => 'Access denied'],
                ], \JSON_THROW_ON_ERROR));
            }

            self::fail('Unexpected VK HTTP call: '.$url);
        });

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('flush');

        $result = $this->crossposter($http, $em)->crosspostEntry($entry);

        self::assertSame('failed', $result);
        self::assertNull($entry->getVkPostId());
        self::assertSame('Access denied', $entry->getVkCrosspostError());
    }

    public function testUpdateEntryEditsExistingVkPost(): void
    {
        $entry = $this->publishedEntry();
        $entry->setVkPostId(88);

        $http = new MockHttpClient(static function (string $method, string $url): MockResponse {
            if (str_contains($url, 'wall.edit')) {
                return new MockResponse(json_encode(['response' => 1], \JSON_THROW_ON_ERROR));
            }

            self::fail('Unexpected VK HTTP call: '.$url);
        });

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $result = $this->crossposter($http, $em)->updateEntry($entry);

        self::assertSame('updated', $result);
        self::assertNull($entry->getVkCrosspostError());
    }

    public function testCrosspostDueAggregatesResults(): void
    {
        $ready = $this->publishedEntry();
        $ready->setVkCrosspostRequested(true);

        $repo = $this->createMock(ChronicleEntryRepository::class);
        $repo->method('findDueForVkCrosspost')->willReturn([$ready]);

        $http = new MockHttpClient(static function (string $method, string $url): MockResponse {
            if (str_contains($url, 'wall.post')) {
                return new MockResponse(json_encode(['response' => ['post_id' => 777]], \JSON_THROW_ON_ERROR));
            }

            self::fail('Unexpected VK HTTP call: '.$url);
        });

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('flush');

        $stats = $this->crossposter($http, $em, $repo)->crosspostDue(5);

        self::assertSame(1, $stats['posted']);
        self::assertSame(0, $stats['skipped']);
        self::assertSame(0, $stats['failed']);
        self::assertSame(0, $stats['deferred']);
    }

    private function publishedEntry(): ChronicleEntry
    {
        $entry = new ChronicleEntry();
        $entry->setTitle('T');
        $entry->setSlug('t-'.bin2hex(random_bytes(3)));
        $entry->setShortHash(substr(bin2hex(random_bytes(4)), 0, 8));
        $entry->setStatus(ChronicleStatus::Published);
        $entry->setPublishedAt(new \DateTimeImmutable('-1 hour'));

        return $entry;
    }

    private function crossposter(
        MockHttpClient $http,
        ?EntityManagerInterface $em = null,
        ?ChronicleEntryRepository $entries = null,
    ): ChronicleVkCrossposter {
        $creds = $this->enabledCredentials();
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/p/abcd1234');

        return new ChronicleVkCrossposter(
            $em ?? $this->createMock(EntityManagerInterface::class),
            $entries ?? $this->createMock(ChronicleEntryRepository::class),
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
            ownerIdEnv: '-12345',
            enabledEnv: '1',
        );
    }
}
