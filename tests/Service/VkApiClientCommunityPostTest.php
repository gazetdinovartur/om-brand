<?php

namespace App\Tests\Service;

use App\Service\VkApiClient;
use App\Service\VkCredentials;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class VkApiClientCommunityPostTest extends TestCase
{
    public function testWallPostUsesFromGroupAndCommunityToken(): void
    {
        $seen = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            parse_str($options['body'] ?? '', $body);
            $seen = $body;

            return new MockResponse(json_encode(['response' => ['post_id' => 42]], \JSON_THROW_ON_ERROR));
        });

        $dir = sys_get_temp_dir().'/vk-api-'.bin2hex(random_bytes(4));
        mkdir($dir.'/vk', 0700, true);

        $creds = new VkCredentials(
            projectDir: $dir,
            communityTokenEnv: 'community-token',
            ownerIdEnv: '-999',
            enabledEnv: '1',
        );

        $client = new VkApiClient($http, $creds);
        $postId = $client->wallPost(-999, 'hello');

        self::assertSame(42, $postId);
        self::assertSame('1', (string) ($seen['from_group'] ?? ''));
        self::assertSame('-999', (string) ($seen['owner_id'] ?? ''));
        self::assertSame('community-token', (string) ($seen['access_token'] ?? ''));
        self::assertTrue($creds->canCrosspost());
        self::assertFalse($creds->canSync());
    }

    public function testResolvesScreenNameRequiresUserToken(): void
    {
        $http = new MockHttpClient(static function (): MockResponse {
            self::fail('HTTP must not be called without user token');
        });

        $dir = sys_get_temp_dir().'/vk-api-'.bin2hex(random_bytes(4));
        mkdir($dir.'/vk', 0700, true);

        $creds = new VkCredentials(
            projectDir: $dir,
            communityTokenEnv: 'community-token',
            ownerIdEnv: '-myscreen',
            enabledEnv: '1',
        );

        $this->expectException(\App\Service\VkApiException::class);
        (new VkApiClient($http, $creds))->resolveOwnerId();
    }

    public function testResolvesScreenNameToNegativeOwnerId(): void
    {
        $http = new MockHttpClient(static function (string $method, string $url): MockResponse {
            if (str_contains($url, 'utils.resolveScreenName')) {
                return new MockResponse(json_encode([
                    'response' => ['type' => 'group', 'object_id' => 555],
                ], \JSON_THROW_ON_ERROR));
            }

            self::fail('Unexpected: '.$url);
        });

        $dir = sys_get_temp_dir().'/vk-api-'.bin2hex(random_bytes(4));
        mkdir($dir.'/vk', 0700, true);

        $creds = new VkCredentials(
            projectDir: $dir,
            communityTokenEnv: 'community-token',
            userTokenEnv: 'user-token',
            ownerIdEnv: '-myscreen',
            enabledEnv: '1',
        );

        $client = new VkApiClient($http, $creds);
        self::assertSame(-555, $client->resolveOwnerId());
        self::assertTrue($creds->canSync());
    }

    public function testSyncOwnerPrefersSyncEnvOverOwner(): void
    {
        $dir = sys_get_temp_dir().'/vk-api-'.bin2hex(random_bytes(4));
        mkdir($dir.'/vk', 0700, true);
        file_put_contents($dir.'/vk/user_access_token', "user-token\n");

        $creds = new VkCredentials(
            projectDir: $dir,
            communityTokenEnv: 'community-token',
            userTokenEnv: 'user-token',
            ownerIdEnv: '-999',
            syncOwnerIdEnv: '253848163',
            enabledEnv: '1',
        );

        self::assertSame(-999, $creds->ownerId());
        self::assertSame(253848163, $creds->syncOwnerId());
        self::assertTrue($creds->isCommunityTarget());
        self::assertTrue($creds->canCrosspost());
        self::assertTrue($creds->canSync());
    }
}
