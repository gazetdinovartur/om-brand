<?php

namespace App\Tests\Service;

use App\Repository\ChronicleEntryRepository;
use App\Service\ChronicleHashGenerator;
use PHPUnit\Framework\TestCase;

final class ChronicleHashGeneratorTest extends TestCase
{
    public function testGeneratesEightCharHash(): void
    {
        $repo = $this->createMock(ChronicleEntryRepository::class);
        $repo->method('isShortHashTaken')->willReturn(false);

        $generator = new ChronicleHashGenerator($repo);
        $hash = $generator->generateUnique();

        self::assertSame(8, strlen($hash));
        self::assertMatchesRegularExpression('/^[a-z0-9]{8}$/', $hash);
    }

    public function testRetriesWhenTaken(): void
    {
        $repo = $this->createMock(ChronicleEntryRepository::class);
        $repo->method('isShortHashTaken')
            ->willReturnOnConsecutiveCalls(true, true, false);

        $generator = new ChronicleHashGenerator($repo);
        $hash = $generator->generateUnique();

        self::assertSame(8, strlen($hash));
    }
}
