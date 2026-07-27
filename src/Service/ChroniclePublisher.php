<?php

namespace App\Service;

use App\Entity\ChronicleEntry;
use App\Enum\ChronicleStatus;
use App\Repository\ChronicleEntryRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ChroniclePublisher
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ChronicleEntryRepository $entries,
        private readonly ChronicleVkCrossposter $vkCrossposter,
        private readonly NewPostNotifier $newPostNotifier,
    ) {
    }

    public function publishScheduled(): int
    {
        $ready = $this->entries->findScheduledReady();
        $count = 0;

        foreach ($ready as $entry) {
            $entry->setStatus(ChronicleStatus::Published);
            ++$count;
        }

        if ($count > 0) {
            $this->em->flush();
            foreach ($ready as $entry) {
                if ($entry->isVisibleInFeed()) {
                    $this->vkCrossposter->crosspostEntry($entry);
                    if (!$entry->hasNotifiedSubscribers()) {
                        $this->newPostNotifier->notifyPublished($entry);
                    }
                }
            }
        }

        return $count;
    }

    public function publishNow(ChronicleEntry $entry, ?\DateTimeImmutable $at = null): void
    {
        $wasAlreadyVisibleInFeed = $entry->isVisibleInFeed();

        $entry->setStatus(ChronicleStatus::Published);
        $entry->setPublishedAt($at ?? new \DateTimeImmutable());
        $this->em->flush();

        if ($entry->isVisibleInFeed()) {
            $this->vkCrossposter->crosspostEntry($entry);
            if (!$wasAlreadyVisibleInFeed || !$entry->hasNotifiedSubscribers()) {
                $this->newPostNotifier->notifyPublished($entry);
            }
        }
    }
}
