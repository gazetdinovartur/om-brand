<?php

namespace App\Tests\Service;

use App\Entity\ChronicleEntry;
use App\Enum\ChronicleStatus;
use App\Service\ChroniclePublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ChroniclePublisherTest extends KernelTestCase
{
    private ChroniclePublisher $publisher;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->publisher = static::getContainer()->get(ChroniclePublisher::class);
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testPublishScheduledNotifiesVisibleEntries(): void
    {
        $entry = $this->draftEntry();
        $entry->setStatus(ChronicleStatus::Scheduled);
        $entry->setPublishedAt(new \DateTimeImmutable('-5 minutes'));
        $this->em->persist($entry);
        $this->em->flush();

        $count = $this->publisher->publishScheduled();
        $this->em->refresh($entry);

        self::assertSame(1, $count);
        self::assertSame(ChronicleStatus::Published, $entry->getStatus());
        self::assertTrue($entry->hasNotifiedSubscribers());
    }

    public function testPublishNowNotifiesOnFirstPublicPublish(): void
    {
        $entry = $this->draftEntry();
        $this->em->persist($entry);
        $this->em->flush();

        $this->publisher->publishNow($entry);
        $this->em->refresh($entry);

        self::assertSame(ChronicleStatus::Published, $entry->getStatus());
        self::assertNotNull($entry->getPublishedAt());
        self::assertTrue($entry->hasNotifiedSubscribers());
    }

    public function testPublishNowDoesNotNotifyAgainWhenAlreadyVisibleInFeed(): void
    {
        $entry = $this->publishedVisibleEntry();
        $entry->markSubscribersNotified(new \DateTimeImmutable('-1 hour'));
        $this->em->persist($entry);
        $this->em->flush();
        $previous = $entry->getSubscribersNotifiedAt();

        $this->publisher->publishNow($entry);
        $this->em->refresh($entry);

        self::assertEquals($previous?->format('c'), $entry->getSubscribersNotifiedAt()?->format('c'));
    }

    public function testPublishNowNotifiesLegacyPublishedEntryWithoutNotifyMarker(): void
    {
        $entry = $this->publishedVisibleEntry();
        $this->em->persist($entry);
        $this->em->flush();

        $this->publisher->publishNow($entry);
        $this->em->refresh($entry);

        self::assertTrue($entry->hasNotifiedSubscribers());
    }

    private function draftEntry(): ChronicleEntry
    {
        $entry = new ChronicleEntry();
        $entry->setTitle('Draft');
        $entry->setSlug('draft-'.bin2hex(random_bytes(4)));
        $entry->setShortHash(substr(bin2hex(random_bytes(4)), 0, 8));
        $entry->setStatus(ChronicleStatus::Draft);

        return $entry;
    }

    private function publishedVisibleEntry(): ChronicleEntry
    {
        $entry = new ChronicleEntry();
        $entry->setTitle('Published');
        $entry->setSlug('pub-'.bin2hex(random_bytes(4)));
        $entry->setShortHash(substr(bin2hex(random_bytes(4)), 0, 8));
        $entry->setStatus(ChronicleStatus::Published);
        $entry->setPublishedAt(new \DateTimeImmutable('-1 day'));

        return $entry;
    }
}
