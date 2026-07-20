<?php

declare(strict_types=1);

namespace App\Content;

/**
 * Loads config/content/catalog.json — eras, tags, series, channels.
 */
final class ContentCatalog
{
    /** @var array<string, mixed>|null */
    private ?array $data = null;

    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        if (null === $this->data) {
            $path = $this->projectDir.'/config/content/catalog.json';
            $raw = file_get_contents($path);
            if (false === $raw) {
                throw new \RuntimeException('Cannot read content catalog: '.$path);
            }
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!\is_array($decoded)) {
                throw new \RuntimeException('Invalid content catalog JSON');
            }
            $this->data = $decoded;
        }

        return $this->data;
    }

    /** @return list<array<string, mixed>> */
    public function eras(): array
    {
        /** @var list<array<string, mixed>> $eras */
        $eras = $this->all()['eras'] ?? [];

        return $eras;
    }

    /** @return list<array<string, mixed>> */
    public function themeTags(): array
    {
        /** @var list<array<string, mixed>> $tags */
        $tags = $this->all()['theme_tags'] ?? [];

        return $tags;
    }

    /** @return list<array<string, mixed>> */
    public function channelTags(): array
    {
        /** @var list<array<string, mixed>> $tags */
        $tags = $this->all()['channel_tags'] ?? [];

        return $tags;
    }

    /** @return list<array<string, mixed>> */
    public function series(): array
    {
        /** @var list<array<string, mixed>> $series */
        $series = $this->all()['series'] ?? [];

        return $series;
    }

    /** @return array<string, mixed>|null */
    public function externalPaths(): ?array
    {
        $paths = $this->all()['external_paths'] ?? null;

        return \is_array($paths) ? $paths : null;
    }
}
