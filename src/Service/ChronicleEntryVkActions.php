<?php

namespace App\Service;

use App\Entity\ChronicleEntry;
use Doctrine\ORM\EntityManagerInterface;

final class ChronicleEntryVkActions
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ChronicleVkCrossposter $crossposter,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function applyFromPayload(ChronicleEntry $entry, array $payload): void
    {
        if (\array_key_exists('vkCrosspostRequested', $payload)) {
            $entry->setVkCrosspostRequested((bool) $payload['vkCrosspostRequested']);
        }

        $this->em->flush();

        if (!empty($payload['vkUpdateToVk']) && null !== $entry->getVkPostId()) {
            $this->crossposter->updateEntry($entry);

            return;
        }

        if ($entry->isVkCrosspostRequested() && null === $entry->getVkPostId()) {
            $this->crossposter->crosspostEntry($entry);
        }
    }
}
