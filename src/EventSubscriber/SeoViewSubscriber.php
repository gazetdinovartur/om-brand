<?php

namespace App\EventSubscriber;

use App\Seo\SeoMetadata;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

final class SeoViewSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => ['onView', 0],
        ];
    }

    public function onView(ViewEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $result = $event->getControllerResult();
        if (!\is_array($result) || !isset($result['seo']) || !$result['seo'] instanceof SeoMetadata) {
            return;
        }

        $this->twig->addGlobal('seo', $result['seo']);
    }
}
