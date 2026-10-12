<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\TwigGlobalSubscriber;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;

final class TwigGlobalSubscriberTest extends TestCase
{
    public function testSkipsWebauthnAndAdminRoutes(): void
    {
        $reflection = new ReflectionClass(TwigGlobalSubscriber::class);
        $subscriber = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('shouldSkipRoute');

        foreach ([
            'webauthn.controller.security.admin.request.options',
            'Webauthn\\Bundle\\Controller\\AssertionRequestController',
            'admin_login',
            'web_library_login',
            '_profiler',
        ] as $route) {
            self::assertTrue($method->invoke($subscriber, $route), $route);
        }

        self::assertFalse($method->invoke($subscriber, 'web_library'));
    }

    public function testResolvesEasyAdminProxiedLibraryRoute(): void
    {
        $reflection = new ReflectionClass(TwigGlobalSubscriber::class);
        $subscriber = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('resolveSiteRoute');

        $proxied = Request::create('/admin', 'GET', ['routeName' => 'web_library']);
        $proxied->attributes->set('_route', 'admin');
        self::assertSame('web_library', $method->invoke($subscriber, $proxied));

        $plainAdmin = Request::create('/admin');
        $plainAdmin->attributes->set('_route', 'admin');
        self::assertSame('admin', $method->invoke($subscriber, $plainAdmin));
    }
}
