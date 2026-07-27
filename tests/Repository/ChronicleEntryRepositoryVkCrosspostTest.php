<?php

namespace App\Tests\Repository;

use App\Entity\ChronicleEntry;
use App\Enum\ChronicleStatus;
use App\Repository\ChronicleEntryRepository;
use App\Service\VkCredentials;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ChronicleEntryRepositoryVkCrosspostTest extends KernelTestCase
{
    private ChronicleEntryRepository $entries;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entries = static::getContainer()->get(ChronicleEntryRepository::class);
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testFindDueForVkCrosspostIncludesPublishedReadyEntry(): void
    {
        $entry = $this->persistEntry('due-published');
        $entry->setStatus(ChronicleStatus::Published);
        $entry->setPublishedAt(new \DateTimeImmutable('-1 hour'));
        $entry->setVkCrosspostRequested(true);
        $this->em->flush();

        $due = $this->entries->findDueForVkCrosspost(20);
        $ids = array_map(static fn (ChronicleEntry $e): int => (int) $e->getId(), $due);

        self::assertContains((int) $entry->getId(), $ids);
    }

    public function testFindDueForVkCrosspostIncludesScheduledEntry(): void
    {
        $entry = $this->persistEntry('due-scheduled');
        $entry->setStatus(ChronicleStatus::Scheduled);
        $entry->setPublishedAt(new \DateTimeImmutable('+2 days'));
        $entry->setVkCrosspostRequested(true);
        $this->em->flush();

        $due = $this->entries->findDueForVkCrosspost(20);
        $ids = array_map(static fn (ChronicleEntry $e): int => (int) $e->getId(), $due);

        self::assertContains((int) $entry->getId(), $ids);
    }

    public function testFindDueForVkCrosspostSkipsWithoutRequestFlag(): void
    {
        $entry = $this->persistEntry('no-request');
        $entry->setStatus(ChronicleStatus::Published);
        $entry->setPublishedAt(new \DateTimeImmutable('-1 hour'));
        $entry->setVkCrosspostRequested(false);
        $this->em->flush();

        $due = $this->entries->findDueForVkCrosspost(20);
        $ids = array_map(static fn (ChronicleEntry $e): int => (int) $e->getId(), $due);

        self::assertNotContains((int) $entry->getId(), $ids);
    }

    public function testFindDueForVkCrosspostSkipsImportedFromVk(): void
    {
        $entry = $this->persistEntry('imported');
        $entry->setSourceKey('vk:wall:'.random_int(100000, 999999));
        $entry->setStatus(ChronicleStatus::Published);
        $entry->setPublishedAt(new \DateTimeImmutable('-1 hour'));
        $entry->setVkCrosspostRequested(true);
        $this->em->flush();

        $due = $this->entries->findDueForVkCrosspost(20);
        $ids = array_map(static fn (ChronicleEntry $e): int => (int) $e->getId(), $due);

        self::assertNotContains((int) $entry->getId(), $ids);
    }

    public function testFindDueForVkCrosspostSkipsPendingMarker(): void
    {
        $entry = $this->persistEntry('pending-marker');
        $entry->setStatus(ChronicleStatus::Published);
        $entry->setPublishedAt(new \DateTimeImmutable('-1 hour'));
        $entry->setVkCrosspostRequested(true);
        $entry->setVkCrosspostError(VkCredentials::PENDING_MARKER);
        $this->em->flush();

        $due = $this->entries->findDueForVkCrosspost(20);
        $ids = array_map(static fn (ChronicleEntry $e): int => (int) $e->getId(), $due);

        self::assertNotContains((int) $entry->getId(), $ids);
    }

    private function persistEntry(string $suffix): ChronicleEntry
    {
        $unique = bin2hex(random_bytes(4));
        $entry = new ChronicleEntry();
        $entry->setTitle('VK due '.$suffix.' '.$unique);
        $entry->setSlug('vk-due-'.$suffix.'-'.$unique);
        $entry->setShortHash(substr($unique, 0, 8));
        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }
}
