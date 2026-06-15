<?php

namespace App\EventSubscriber;

use App\Service\TwigSiteGlobalsProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

final class CustomErrorPageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly TwigSiteGlobalsProvider $twigSiteGlobalsProvider,
        #[Autowire('%kernel.debug%')]
        private readonly bool $debug,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 100],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest() || $event->hasResponse()) {
            return;
        }

        $throwable = $event->getThrowable();
        $statusCode = $this->resolveStatusCode($throwable);

        if (404 === $statusCode) {
            $this->renderErrorPage($event, 'bundles/TwigBundle/Exception/error404.html.twig', 404);

            return;
        }

        if ($statusCode >= 500 && !$this->debug) {
            $this->renderErrorPage($event, 'bundles/TwigBundle/Exception/error500.html.twig', 500);
        }
    }

    private function renderErrorPage(ExceptionEvent $event, string $template, int $statusCode): void
    {
        $request = $event->getRequest();
        $this->twigSiteGlobalsProvider->apply($this->twig, $request, 'web_home');

        $event->setResponse(new Response(
            $this->twig->render($template),
            $statusCode,
        ));
    }

    private function resolveStatusCode(\Throwable $throwable): int
    {
        if ($throwable instanceof HttpExceptionInterface) {
            return $throwable->getStatusCode();
        }

        do {
            if ($throwable instanceof HttpExceptionInterface) {
                return $throwable->getStatusCode();
            }
            $throwable = $throwable->getPrevious();
        } while (null !== $throwable);

        return 500;
    }
}
