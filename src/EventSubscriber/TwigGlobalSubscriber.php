<?php

namespace App\EventSubscriber;

use App\Service\TwigSiteGlobalsProvider;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

final class TwigGlobalSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly TwigSiteGlobalsProvider $twigSiteGlobalsProvider,
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onController',
        ];
    }

    public function onController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = (string) $event->getRequest()->attributes->get('_route');
        if ($this->shouldSkipRoute($route)) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }

        $this->twigSiteGlobalsProvider->apply($this->twig, $request, $route);
    }

    private function shouldSkipRoute(string $route): bool
    {
        return str_starts_with($route, 'admin')
            || str_starts_with($route, '_profiler')
            || str_starts_with($route, '_wdt')
            || str_starts_with($route, '_fragment');
    }
}
