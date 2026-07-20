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
        }

        return $count;
    }

    public function publishNow(ChronicleEntry $entry, ?\DateTimeImmutable $at = null): void
    {
        $entry->setStatus(ChronicleStatus::Published);
        $entry->setPublishedAt($at ?? new \DateTimeImmutable());
        $this->em->flush();
    }
}
