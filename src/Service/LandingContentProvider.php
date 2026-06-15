<?php

namespace App\Service;

use App\Entity\ContentBlock;
use App\Repository\ContentBlockRepository;

class LandingContentProvider
{
    /** @var array<string, ContentBlock>|null */
    private ?array $indexed = null;

    public function __construct(
        private readonly ContentBlockRepository $contentBlockRepository,
    ) {
    }

    /** @return list<ContentBlock> */
    public function getVisibleBlocks(): array
    {
        return $this->contentBlockRepository->findVisibleOrdered();
    }

    public function getBlock(string $slug): ?ContentBlock
    {
        $this->loadIndexed();

        return $this->indexed[$slug] ?? null;
    }

    private function loadIndexed(): void
    {
        if (null !== $this->indexed) {
            return;
        }

        $this->indexed = [];
        foreach ($this->getVisibleBlocks() as $block) {
            $this->indexed[$block->getSlug()] = $block;
        }
    }
}
