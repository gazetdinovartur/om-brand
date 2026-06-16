<?php

namespace App\Service;

use App\Entity\ContentBlock;
use App\Entity\SiteSettings;
use App\Repository\ContentBlockRepository;
use App\Repository\SiteSettingsRepository;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Per-request cache for public site data (deduplicates DB reads within one request).
 * Reset after each HTTP request so PHP-FPM workers do not serve stale admin edits.
 */
final class PublicSiteContext implements ResetInterface
{
    private ?SiteSettings $settings = null;

    /** @var list<ContentBlock>|null */
    private ?array $visibleBlocks = null;

    /** @var array<string, ContentBlock>|null */
    private ?array $blocksBySlug = null;

    public function __construct(
        private readonly SiteSettingsRepository $siteSettingsRepository,
        private readonly ContentBlockRepository $contentBlockRepository,
    ) {
    }

    public function getSettings(): SiteSettings
    {
        return $this->settings ??= $this->siteSettingsRepository->getSettings();
    }

    /** @return list<ContentBlock> */
    public function getVisibleBlocks(): array
    {
        return $this->visibleBlocks ??= $this->contentBlockRepository->findVisibleOrdered();
    }

    /** @return array<string, ContentBlock> */
    public function getBlocksBySlug(): array
    {
        if (null !== $this->blocksBySlug) {
            return $this->blocksBySlug;
        }

        $this->blocksBySlug = [];
        foreach ($this->getVisibleBlocks() as $block) {
            $this->blocksBySlug[$block->getSlug()] = $block;
        }

        return $this->blocksBySlug;
    }

    /** @param list<string> $slugs @return array<string, ContentBlock> */
    public function getBlocksBySlugFiltered(array $slugs): array
    {
        $filtered = [];
        foreach ($this->getBlocksBySlug() as $slug => $block) {
            if (\in_array($slug, $slugs, true)) {
                $filtered[$slug] = $block;
            }
        }

        return $filtered;
    }

    public function reset(): void
    {
        $this->settings = null;
        $this->visibleBlocks = null;
        $this->blocksBySlug = null;
    }
}
