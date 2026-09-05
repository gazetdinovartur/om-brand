<?php

namespace App\Service;

use App\Entity\ChronicleEntry;
use Doctrine\ORM\EntityManagerInterface;

final class ChronicleEntryTelegramActions
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ChronicleTelegramCrossposter $crossposter,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function applyFromPayload(ChronicleEntry $entry, array $payload): void
    {
        if (\array_key_exists('tgCrosspostRequested', $payload)) {
            $entry->setTgCrosspostRequested((bool) $payload['tgCrosspostRequested']);
        }

        $this->em->flush();

        if (!empty($payload['tgUpdateToChannel']) && null !== $entry->getTgMessageId()) {
            $this->crossposter->updateEntry($entry);

            return;
        }

        if ($entry->isTgCrosspostRequested() && null === $entry->getTgMessageId()) {
            $this->crossposter->crosspostEntry($entry);
        }
    }
}
