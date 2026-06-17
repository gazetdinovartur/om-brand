<?php

namespace App\EventSubscriber;

use App\Entity\CaseStudy;
use App\Entity\SiteSettings;
use App\Service\ImageOptimizer;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class EntityImageOptimizerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ImageOptimizer $imageOptimizer,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityPersistedEvent::class => 'optimizeImages',
            BeforeEntityUpdatedEvent::class => 'optimizeImages',
        ];
    }

    public function optimizeImages(BeforeEntityPersistedEvent|BeforeEntityUpdatedEvent $event): void
    {
        $entity = $event->getEntityInstance();

        if ($entity instanceof SiteSettings) {
            $this->optimizeAvatar($entity);

            return;
        }

        if ($entity instanceof CaseStudy) {
            $this->optimizeCaseCover($entity);
        }
    }

    private function optimizeAvatar(SiteSettings $settings): void
    {
        $path = $settings->getAvatarPath();
        if (null === $path || '' === $path || str_ends_with($path, '.webp')) {
            return;
        }

        $relative = str_contains($path, '/') ? $path : 'avatars/'.$path;
        $result = $this->imageOptimizer->optimizeToWebp(
            $relative,
            maxWidth: 1200,
            thumbWidth: 160,
            quality: 92,
        );
        $settings->setAvatarPath(basename($result['path']));
    }

    private function optimizeCaseCover(CaseStudy $caseStudy): void
    {
        $path = $caseStudy->getCoverImagePath();
        if (null === $path || '' === $path || str_ends_with($path, '.webp')) {
            return;
        }

        $result = $this->imageOptimizer->optimizeToWebp($path, maxWidth: 1200, thumbWidth: 640);
        $caseStudy->setCoverImagePath($result['path']);
    }
}
