<?php

namespace App\Tests\Controller;

use App\Controller\ChronicleShortLinkController;
use App\Entity\ChronicleEntry;
use App\Repository\ChronicleEntryRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ChronicleShortLinkControllerTest extends TestCase
{
    public function testUnknownHashThrows404(): void
    {
        $repo = $this->createMock(ChronicleEntryRepository::class);
        $repo->method('findByShortHash')->with('00000000')->willReturn(null);

        $controller = $this->createController();

        $this->expectException(NotFoundHttpException::class);
        $controller->redirectToEntry('00000000', $repo);
    }

    public function testKnownHashRedirectsPermanently(): void
    {
        $entry = new ChronicleEntry();
        $entry->setSlug('my-post');
        $entry->setShortHash('abcd1234');

        $repo = $this->createMock(ChronicleEntryRepository::class);
        $repo->method('findByShortHash')->with('abcd1234')->willReturn($entry);

        $controller = $this->createController();
        $response = $controller->redirectToEntry('abcd1234', $repo);

        self::assertSame(Response::HTTP_MOVED_PERMANENTLY, $response->getStatusCode());
        self::assertStringContainsString('/chronicle/my-post', $response->headers->get('Location') ?? '');
    }

    private function createController(): ChronicleShortLinkController
    {
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')
            ->with('web_chronicle_show', ['slug' => 'my-post'])
            ->willReturn('/chronicle/my-post');

        $container = new \Symfony\Component\DependencyInjection\Container();
        $container->set('router', $router);

        $controller = new ChronicleShortLinkController();
        $controller->setContainer($container);

        return $controller;
    }
}
