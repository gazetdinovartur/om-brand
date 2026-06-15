<?php

namespace App\EventSubscriber;

use App\Repository\ContentBlockRepository;
use App\Repository\SiteSettingsRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

final class TwigGlobalSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly SiteSettingsRepository $siteSettingsRepository,
        private readonly ContentBlockRepository $contentBlockRepository,
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
        if (str_starts_with($route, 'admin') || str_starts_with($route, '_')) {
            return;
        }

        $settings = $this->siteSettingsRepository->getSettings();
        $blocksBySlug = [];

        foreach ($this->contentBlockRepository->findVisibleOrdered() as $block) {
            if (str_starts_with($block->getSlug(), 'footer_')) {
                $blocksBySlug[$block->getSlug()] = $block;
            }
        }

        $this->twig->addGlobal('settings', $settings);
        $this->twig->addGlobal('blocksBySlug', $blocksBySlug);
    }
}
