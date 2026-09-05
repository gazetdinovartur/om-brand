<?php

namespace App\Tests\Service;

use App\Service\VkCredentials;
use App\Service\VkIdOAuth;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class VkIdOAuthTest extends TestCase
{
    public function testExtractsCodeAndDeviceIdFromBlankUrl(): void
    {
        $oauth = $this->oauth();
        $parsed = $oauth->extractAuthCodePayload(
            'https://oauth.vk.com/blank.html?code=vk2.a.code&device_id=dev123&state=abc',
        );

        self::assertNotNull($parsed);
        self::assertSame('vk2.a.code', $parsed['code']);
        self::assertSame('dev123', $parsed['device_id']);
        self::assertSame('abc', $parsed['state']);
    }

    public function testExchangesCodeForAccessToken(): void
    {
        $http = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertStringContainsString('oauth2/auth', $url);
            parse_str($options['body'] ?? '', $body);
            self::assertSame('authorization_code', $body['grant_type'] ?? '');
            self::assertSame('verifier', $body['code_verifier'] ?? '');
            self::assertSame('http://localhost:8085/admin/oauth/vk/callback', $body['redirect_uri'] ?? '');

            return new MockResponse(json_encode([
                'access_token' => 'access-xyz',
                'refresh_token' => 'refresh-xyz',
                'user_id' => 42,
                'expires_in' => 3600,
            ], \JSON_THROW_ON_ERROR));
        });

        $result = $this->oauth($http)->exchangeCode('code', 'device', 'verifier', 'state');

        self::assertSame('access-xyz', $result['access_token']);
        self::assertSame(42, $result['user_id']);
        self::assertSame('device', $result['device_id']);
    }

    public function testRedirectUriUsesSiteUrl(): void
    {
        self::assertSame(
            'http://localhost:8085/admin/oauth/vk/callback',
            $this->oauth()->redirectUri(),
        );
    }

    private function oauth(?MockHttpClient $http = null): VkIdOAuth
    {
        $dir = sys_get_temp_dir().'/vk-id-'.bin2hex(random_bytes(4));
        mkdir($dir.'/vk', 0700, true);

        return new VkIdOAuth(
            new VkCredentials(projectDir: $dir, appIdEnv: '12345'),
            $http ?? new MockHttpClient(),
            'http://localhost:8085',
        );
    }
}
