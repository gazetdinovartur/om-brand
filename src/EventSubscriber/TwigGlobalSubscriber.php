<?php

namespace App\EventSubscriber;

use App\Service\TwigSiteGlobalsProvider;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
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

        $request = $event->getRequest();
        $route = $this->resolveSiteRoute($request);
        if ($this->shouldSkipRoute($route)) {
            return;
        }

        $current = $this->requestStack->getCurrentRequest();
        if (null === $current) {
            return;
        }

        $this->twigSiteGlobalsProvider->apply($this->twig, $current, $route);
    }

    /**
     * EasyAdmin linkToRoute keeps _route=admin and executes the target via ?routeName=.
     * Public site templates still need Twig globals in that case.
     */
    private function resolveSiteRoute(Request $request): string
    {
        $route = (string) $request->attributes->get('_route');
        $proxied = $request->query->getString('routeName');
        if (str_starts_with($route, 'admin') && str_starts_with($proxied, 'web_')) {
            return $proxied;
        }

        return $route;
    }

    private function shouldSkipRoute(string $route): bool
    {
        return str_starts_with($route, 'admin')
            || str_starts_with($route, 'webauthn.')
            || str_starts_with($route, 'Webauthn\\')
            || 'web_library_login' === $route
            || str_starts_with($route, '_profiler')
            || str_starts_with($route, '_wdt')
            || str_starts_with($route, '_fragment');
    }
}
