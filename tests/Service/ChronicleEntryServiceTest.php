<?php

namespace App\Tests\Service;

use App\Entity\ChronicleBlock;
use App\Entity\ChronicleEntry;
use App\Entity\ChronicleTag;
use App\Enum\ChronicleBlockType;
use App\Repository\ChronicleEntryRepository;
use App\Repository\ChronicleEraRepository;
use App\Repository\ChronicleSeriesRepository;
use App\Repository\ChronicleTagRepository;
use App\Service\ChronicleEntryService;
use App\Service\ChronicleHashGenerator;
use App\Service\ChronicleMarkdownRenderer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class ChronicleEntryServiceTest extends TestCase
{
    public function testSerializeIncludesBlockFields(): void
    {
        $entry = new ChronicleEntry();
        $entry->setTitle('Test');
        $entry->setSlug('test');
        $entry->setShortHash('abcd1234');

        $block = new ChronicleBlock();
        $block->setType(ChronicleBlockType::Paragraph);
        $block->setBody('hello');
        $block->setSortOrder(0);
        $entry->addBlock($block);

        $hashRepo = $this->createMock(ChronicleEntryRepository::class);
        $hashRepo->method('isShortHashTaken')->willReturn(false);

        $service = new ChronicleEntryService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(ChronicleEraRepository::class),
            $this->createMock(ChronicleSeriesRepository::class),
            $this->createMock(ChronicleTagRepository::class),
            new ChronicleHashGenerator($hashRepo),
            new ChronicleMarkdownRenderer(),
            new AsciiSlugger(),
            $hashRepo,
        );

        $data = $service->serialize($entry);

        self::assertSame('hello', $data['blocks'][0]['body']);
        self::assertSame('paragraph', $data['blocks'][0]['type']);
    }

    public function testApplyPayloadPublishedAtHonorsClientTimezoneOffset(): void
    {
        $previousTz = date_default_timezone_get();
        date_default_timezone_set('Europe/Moscow');

        try {
            $entry = new ChronicleEntry();
            $entry->setTitle('TZ');
            $entry->setSlug('tz');
            $entry->setShortHash('tz123456');

            $hashRepo = $this->createMock(ChronicleEntryRepository::class);
            $hashRepo->method('isShortHashTaken')->willReturn(false);

            $service = new ChronicleEntryService(
                $this->createMock(EntityManagerInterface::class),
                $this->createMock(ChronicleEraRepository::class),
                $this->createMock(ChronicleSeriesRepository::class),
                $this->createMock(ChronicleTagRepository::class),
                new ChronicleHashGenerator($hashRepo),
                new ChronicleMarkdownRenderer(),
                new AsciiSlugger(),
                $hashRepo,
            );

            // 15:01 on Urals (UTC+5) = 13:01 in Moscow.
            $service->applyPayload($entry, [
                'publishedAt' => '2026-07-25T15:01:00+05:00',
            ], touchUpdatedAt: false);

            $publishedAt = $entry->getPublishedAt();
            self::assertNotNull($publishedAt);
            self::assertSame('Europe/Moscow', $publishedAt->getTimezone()->getName());
            self::assertSame('2026-07-25 13:01:00', $publishedAt->format('Y-m-d H:i:s'));
            self::assertSame('2026-07-25T13:01:00+03:00', $service->serialize($entry)['publishedAt']);
        } finally {
            date_default_timezone_set($previousTz);
        }
    }
}
