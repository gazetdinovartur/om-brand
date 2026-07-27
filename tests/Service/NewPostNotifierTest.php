<?php

namespace App\Tests\Service;

use App\Entity\ChronicleEntry;
use App\Enum\ChronicleStatus;
use App\Service\NewPostNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class NewPostNotifierTest extends KernelTestCase
{
    private NewPostNotifier $notifier;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->notifier = static::getContainer()->get(NewPostNotifier::class);
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testSkipsWhenEntryNotVisibleInFeed(): void
    {
        $entry = $this->publishedEntry();
        $entry->setIsAdminOnly(true);

        $this->notifier->notifyPublished($entry);

        self::assertNull($entry->getSubscribersNotifiedAt());
    }

    public function testSkipsWhenAlreadyNotifiedWithoutForce(): void
    {
        $entry = $this->publishedEntry();
        $entry->markSubscribersNotified(new \DateTimeImmutable('-1 hour'));
        $previous = $entry->getSubscribersNotifiedAt();

        $this->notifier->notifyPublished($entry);

        self::assertSame($previous, $entry->getSubscribersNotifiedAt());
    }

    public function testNotifyMarksSubscribersNotified(): void
    {
        $entry = $this->publishedEntry();

        $this->notifier->notifyPublished($entry);

        self::assertNotNull($entry->getSubscribersNotifiedAt());
        self::assertTrue($entry->hasNotifiedSubscribers());
    }

    public function testForceUpdatesNotifyMarkerEvenWhenAlreadyNotified(): void
    {
        $entry = $this->publishedEntry();
        $entry->markSubscribersNotified(new \DateTimeImmutable('-1 day'));
        $previous = $entry->getSubscribersNotifiedAt();

        $this->notifier->notifyPublished($entry, force: true);

        self::assertNotNull($entry->getSubscribersNotifiedAt());
        self::assertGreaterThan($previous, $entry->getSubscribersNotifiedAt());
    }

    private function publishedEntry(): ChronicleEntry
    {
        $entry = new ChronicleEntry();
        $entry->setTitle('Notify test');
        $entry->setSlug('notify-'.bin2hex(random_bytes(4)));
        $entry->setShortHash(substr(bin2hex(random_bytes(4)), 0, 8));
        $entry->setStatus(ChronicleStatus::Published);
        $entry->setPublishedAt(new \DateTimeImmutable('-1 hour'));
        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }
}
