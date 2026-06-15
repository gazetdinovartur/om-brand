<?php

namespace App\Service;

class LandingContentProvider
{
    /** @var array<string, \App\Entity\ContentBlock>|null */
    private ?array $indexed = null;

    public function __construct(
        private readonly PublicSiteContext $siteContext,
    ) {
    }

    /** @return list<\App\Entity\ContentBlock> */
    public function getVisibleBlocks(): array
    {
        return $this->siteContext->getVisibleBlocks();
    }

    public function getBlock(string $slug): ?\App\Entity\ContentBlock
    {
        $this->loadIndexed();

        return $this->indexed[$slug] ?? null;
    }

    private function loadIndexed(): void
    {
        if (null !== $this->indexed) {
            return;
        }

        $this->indexed = $this->siteContext->getBlocksBySlug();
    }
}
