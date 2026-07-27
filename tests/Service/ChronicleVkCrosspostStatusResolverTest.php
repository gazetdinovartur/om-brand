<?php

namespace App\Tests\Service;

use App\Entity\ChronicleEntry;
use App\Enum\ChronicleStatus;
use App\Service\ChronicleVkCrosspostStatusResolver;
use App\Service\VkCredentials;
use PHPUnit\Framework\TestCase;

final class ChronicleVkCrosspostStatusResolverTest extends TestCase
{
    public function testPostedWithWallLink(): void
    {
        $entry = $this->publishedEntry();
        $entry->setVkPostId(123);

        $status = $this->resolver()->resolve($entry);

        self::assertSame('posted', $status->kind);
        self::assertSame('опубликовано', $status->label);
        self::assertSame('https://vk.com/wall-12345_123', $status->wallUrl);
    }

    public function testScheduledWhenEntryStatusScheduled(): void
    {
        $entry = $this->publishedEntry();
        $entry->setStatus(ChronicleStatus::Scheduled);
        $entry->setPublishedAt(new \DateTimeImmutable('+2 days'));
        $entry->setVkPostId(55);

        $status = $this->resolver()->resolve($entry);

        self::assertSame('scheduled', $status->kind);
        self::assertSame('запланировано', $status->label);
    }

    public function testImportedFromVk(): void
    {
        $entry = $this->publishedEntry();
        $entry->setSourceKey('vk:wall:99');

        $status = $this->resolver()->resolve($entry);

        self::assertSame('imported', $status->kind);
        self::assertSame('импорт с VK', $status->label);
    }

    public function testPendingWhenRequestedWithoutPost(): void
    {
        $entry = $this->publishedEntry();
        $entry->setVkCrosspostRequested(true);

        $status = $this->resolver()->resolve($entry);

        self::assertSame('pending', $status->kind);
        self::assertSame('ожидает', $status->label);
    }

    public function testErrorFromStoredMessage(): void
    {
        $entry = $this->publishedEntry();
        $entry->setVkCrosspostError('Access denied');

        $status = $this->resolver()->resolve($entry);

        self::assertSame('error', $status->kind);
        self::assertSame('Access denied', $status->hint);
    }

    public function testNoneForLegacyPublishedWithoutVk(): void
    {
        $entry = $this->publishedEntry();

        $status = $this->resolver()->resolve($entry);

        self::assertSame('none', $status->kind);
        self::assertSame('—', $status->label);
    }

    public function testProgressWhenPendingMarker(): void
    {
        $entry = $this->publishedEntry();
        $entry->setVkCrosspostError(VkCredentials::PENDING_MARKER);

        $status = $this->resolver()->resolve($entry);

        self::assertSame('progress', $status->kind);
        self::assertSame('отправка…', $status->label);
    }

    public function testOfflineWhenRequestedButVkNotConnected(): void
    {
        $entry = $this->publishedEntry();
        $entry->setVkCrosspostRequested(true);

        $dir = sys_get_temp_dir().'/vk-status-off-'.bin2hex(random_bytes(4));
        mkdir($dir.'/vk', 0700, true);

        $credentials = new VkCredentials(
            projectDir: $dir,
            enabledEnv: '1',
        );
        $resolver = new ChronicleVkCrosspostStatusResolver($credentials);

        $status = $resolver->resolve($entry);

        self::assertSame('offline', $status->kind);
        self::assertSame('нет VK', $status->label);
    }

    public function testDeferredWhenScheduledBeyondVkWindow(): void
    {
        $entry = $this->publishedEntry();
        $entry->setStatus(ChronicleStatus::Scheduled);
        $entry->setPublishedAt(new \DateTimeImmutable('+40 days'));
        $entry->setVkCrosspostRequested(true);

        $status = $this->resolver()->resolve($entry);

        self::assertSame('deferred', $status->kind);
        self::assertSame('ожидает дату', $status->label);
    }

    public function testDisabledWhenCrosspostFeatureOff(): void
    {
        $entry = $this->publishedEntry();
        $credentials = new VkCredentials(
            projectDir: sys_get_temp_dir(),
            enabledEnv: '0',
        );

        $status = (new ChronicleVkCrosspostStatusResolver($credentials))->resolve($entry);

        self::assertSame('disabled', $status->kind);
        self::assertSame('—', $status->label);
    }

    public function testLegacyPostedShowsEvenWithoutRequestedFlag(): void
    {
        $entry = $this->publishedEntry();
        $entry->setVkPostId(77);
        $entry->setVkCrosspostRequested(false);

        $status = $this->resolver()->resolve($entry);

        self::assertSame('posted', $status->kind);
    }

    private function publishedEntry(): ChronicleEntry
    {
        $entry = new ChronicleEntry();
        $entry->setTitle('T');
        $entry->setSlug('t');
        $entry->setShortHash('abcd1234');
        $entry->setStatus(ChronicleStatus::Published);
        $entry->setPublishedAt(new \DateTimeImmutable('-1 hour'));

        return $entry;
    }

    private function resolver(): ChronicleVkCrosspostStatusResolver
    {
        $dir = sys_get_temp_dir().'/vk-status-'.bin2hex(random_bytes(4));
        mkdir($dir.'/vk', 0700, true);
        file_put_contents($dir.'/vk/user_access_token', "token\n");

        $credentials = new VkCredentials(
            projectDir: $dir,
            userTokenEnv: 'token-from-env',
            ownerIdEnv: '-12345',
            enabledEnv: '1',
        );

        return new ChronicleVkCrosspostStatusResolver($credentials);
    }
}
