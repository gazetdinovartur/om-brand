<?php

namespace App\EventListener;

use App\Entity\CaseStudy;
use App\Entity\CaseStudyImage;
use App\Entity\ChronicleBlock;
use App\Entity\ChronicleBlockImage;
use App\Entity\ChronicleEntry;
use App\Entity\Inquiry;
use App\Entity\SiteSettings;
use App\Service\UploadFileRemover;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::preUpdate)]
final class UploadFileCleanupListener
{
    public function __construct(
        private readonly UploadFileRemover $uploadFileRemover,
    ) {
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        match (true) {
            $entity instanceof CaseStudy => $this->removeCaseStudyFiles($entity),
            $entity instanceof CaseStudyImage => $this->uploadFileRemover->deleteCaseGallery($entity->getImagePath()),
            $entity instanceof ChronicleEntry => $this->removeChronicleEntryFiles($entity),
            $entity instanceof ChronicleBlock => $this->removeChronicleBlockFiles($entity),
            $entity instanceof ChronicleBlockImage => $this->uploadFileRemover->deleteChronicleGallery($entity->getImagePath()),
            $entity instanceof Inquiry => $this->uploadFileRemover->deleteInquiryAttachment($entity->getAttachmentPath()),
            default => null,
        };
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        match (true) {
            $entity instanceof SiteSettings => $this->deleteChangedPath(
                $args,
                'avatarPath',
                fn (?string $path) => $this->uploadFileRemover->deleteAvatar($path),
            ),
            $entity instanceof CaseStudy => $this->updateCaseStudyFiles($args),
            $entity instanceof CaseStudyImage => $this->deleteChangedPath(
                $args,
                'imagePath',
                fn (?string $path) => $this->uploadFileRemover->deleteCaseGallery($path),
            ),
            $entity instanceof ChronicleEntry => $this->updateChronicleEntryFiles($args),
            $entity instanceof ChronicleBlock => $this->updateChronicleBlockFiles($args),
            $entity instanceof ChronicleBlockImage => $this->deleteChangedPath(
                $args,
                'imagePath',
                fn (?string $path) => $this->uploadFileRemover->deleteChronicleGallery($path),
            ),
            default => null,
        };
    }

    private function removeCaseStudyFiles(CaseStudy $caseStudy): void
    {
        $this->uploadFileRemover->deleteCaseCover($caseStudy->getCoverImagePath());
        $this->uploadFileRemover->deleteCaseAudio($caseStudy->getAudioPath());
    }

    private function removeChronicleEntryFiles(ChronicleEntry $entry): void
    {
        $this->uploadFileRemover->deleteChronicleCover($entry->getCoverImagePath());
        $this->uploadFileRemover->deleteChronicleCover($entry->getOgImagePath());
    }

    private function removeChronicleBlockFiles(ChronicleBlock $block): void
    {
        $this->uploadFileRemover->deleteChronicleInline($block->getImagePath());
        $this->uploadFileRemover->deleteChronicleMediaUrl($block->getVideoUrl());
    }

    private function updateCaseStudyFiles(PreUpdateEventArgs $args): void
    {
        $this->deleteChangedPath(
            $args,
            'coverImagePath',
            fn (?string $path) => $this->uploadFileRemover->deleteCaseCover($path),
        );
        $this->deleteChangedPath(
            $args,
            'audioPath',
            fn (?string $path) => $this->uploadFileRemover->deleteCaseAudio($path),
        );
    }

    private function updateChronicleEntryFiles(PreUpdateEventArgs $args): void
    {
        $this->deleteChangedPath(
            $args,
            'coverImagePath',
            fn (?string $path) => $this->uploadFileRemover->deleteChronicleCover($path),
        );
        $this->deleteChangedPath(
            $args,
            'ogImagePath',
            fn (?string $path) => $this->uploadFileRemover->deleteChronicleCover($path),
        );
    }

    private function updateChronicleBlockFiles(PreUpdateEventArgs $args): void
    {
        $this->deleteChangedPath(
            $args,
            'imagePath',
            fn (?string $path) => $this->uploadFileRemover->deleteChronicleInline($path),
        );
        $this->deleteChangedPath(
            $args,
            'videoUrl',
            fn (?string $url) => $this->uploadFileRemover->deleteChronicleMediaUrl($url),
        );
    }

    /**
     * @param callable(?string): void $delete
     */
    private function deleteChangedPath(PreUpdateEventArgs $args, string $field, callable $delete): void
    {
        $changeSet = $args->getEntityChangeSet();
        if (!isset($changeSet[$field])) {
            return;
        }

        [$old, $new] = $changeSet[$field];
        if (!\is_string($old) || '' === $old || $old === $new) {
            return;
        }

        $delete($old);
    }
}
