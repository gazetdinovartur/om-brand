<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\WebhookController;
use App\Service\PaymentOfferService;
use App\Service\PracticeKinescopeService;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

final class KinescopeWebhookAuthTest extends KernelTestCase
{
    public function testEmptyCredentialsAlwaysUnauthorized(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $controller = new WebhookController(
            $container->get(PaymentOfferService::class),
            $container->get(PracticeKinescopeService::class),
            new NullLogger(),
            '',
            '',
        );
        $controller->setContainer($container);

        $response = $controller->kinescope(Request::create(
            '/webhooks/kinescope',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json', 'PHP_AUTH_USER' => 'x', 'PHP_AUTH_PW' => 'y'],
            content: '{"event":"live.finished","data":{}}',
        ));

        self::assertSame(401, $response->getStatusCode());
    }

    public function testEmptyPasswordAlwaysUnauthorized(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $controller = new WebhookController(
            $container->get(PaymentOfferService::class),
            $container->get(PracticeKinescopeService::class),
            new NullLogger(),
            'user',
            '',
        );
        $controller->setContainer($container);

        $response = $controller->kinescope(Request::create(
            '/webhooks/kinescope',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json', 'PHP_AUTH_USER' => 'user', 'PHP_AUTH_PW' => ''],
            content: '{"event":"live.finished","data":{}}',
        ));

        self::assertSame(401, $response->getStatusCode());
    }
}
