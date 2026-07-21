<?php

namespace App\EventSubscriber;

use App\Entity\CaseStudy;
use App\Entity\CaseStudyImage;
use App\Entity\SiteSettings;
use App\Service\ImageOptimizer;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
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
            $this->syncOgFromCover($entity);
            $this->normalizeAudioPath($entity);
            foreach ($entity->getGalleryImages() as $image) {
                $this->optimizeGalleryImage($image);
            }
        }
    }

    private function syncOgFromCover(CaseStudy $caseStudy): void
    {
        $cover = $caseStudy->getCoverImagePath();
        if (null === $cover || '' === $cover) {
            $caseStudy->setOgImagePath(null);

            return;
        }

        $caseStudy->setOgImagePath(basename(str_replace('\\', '/', $cover)));
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
        if (null === $path || '' === $path) {
            return;
        }

        $filename = basename(str_replace('\\', '/', $path));
        $relative = 'cases/'.$filename;

        if (str_ends_with(strtolower($filename), '.webp')) {
            $caseStudy->setCoverImagePath($filename);

            return;
        }

        $result = $this->imageOptimizer->optimizeToWebp(
            $relative,
            maxWidth: 1400,
            thumbWidth: 640,
        );
        $caseStudy->setCoverImagePath(basename($result['path']));
    }

    private function optimizeGalleryImage(CaseStudyImage $image): void
    {
        $path = $image->getImagePath();
        if ('' === $path) {
            return;
        }

        $filename = basename(str_replace('\\', '/', $path));
        $relative = 'cases/gallery/'.$filename;

        if (str_ends_with(strtolower($filename), '.webp')) {
            $image->setImagePath($filename);

            return;
        }

        $result = $this->imageOptimizer->optimizeToWebp($relative, maxWidth: 1400, thumbWidth: 640);
        $image->setImagePath(basename($result['path']));
    }

    private function normalizeAudioPath(CaseStudy $caseStudy): void
    {
        $path = $caseStudy->getAudioPath();
        if (null === $path || '' === $path) {
            return;
        }

        // EasyAdmin FileField stores filename relative to upload dir → keep basename only.
        $caseStudy->setAudioPath(basename(str_replace('\\', '/', $path)));
    }
}
