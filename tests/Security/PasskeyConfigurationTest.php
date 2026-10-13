<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Repository\WebauthnCredentialRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;
use Webauthn\Bundle\Repository\CredentialRecordRepositoryInterface;

final class PasskeyConfigurationTest extends KernelTestCase
{
    public function testWebauthnRoutesAndRepositoryAreWired(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $routes = $container->get(RouterInterface::class)->getRouteCollection();

        self::assertSame(
            '/admin/passkey/login/options',
            $routes->get('webauthn.controller.security.admin.request.options')?->getPath(),
        );
        self::assertSame(
            '/library/passkey/login/options',
            $routes->get('webauthn.controller.security.main.request.options')?->getPath(),
        );
        self::assertSame(
            '/library/passkeys/register/options',
            $routes->get('webauthn.controller.creation.request.site')?->getPath(),
        );
        self::assertSame(
            '/admin/passkeys/register/options',
            $routes->get('webauthn.controller.creation.request.admin')?->getPath(),
        );
        self::assertInstanceOf(
            WebauthnCredentialRepository::class,
            $container->get(CredentialRecordRepositoryInterface::class),
        );

        $config = (string) file_get_contents(dirname(__DIR__, 2).'/config/packages/webauthn.yaml');
        self::assertStringContainsString('App\\Security\\Webauthn\\AdminUserEntityGuesser', $config);
        self::assertStringContainsString('App\\Security\\Webauthn\\SiteUserEntityGuesser', $config);
    }

    public function testPasswordFallbackAndPasskeyAccessRulesRemainTogether(): void
    {
        $config = (string) file_get_contents(dirname(__DIR__, 2).'/config/packages/security.yaml');

        self::assertStringContainsString('form_login:', $config);
        self::assertStringContainsString('login_throttling:', $config);
        self::assertStringContainsString('webauthn:', $config);
        self::assertStringContainsString('^/admin/passkey/login, roles: PUBLIC_ACCESS', $config);
        self::assertStringContainsString('^/library/login, roles: PUBLIC_ACCESS', $config);
        self::assertStringContainsString('^/library, roles: [ROLE_USER, ROLE_ADMIN]', $config);
    }
}
