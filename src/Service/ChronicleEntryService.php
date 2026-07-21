<?php

namespace App\Service;

use App\Entity\ChronicleBlock;
use App\Entity\ChronicleBlockImage;
use App\Entity\ChronicleEntry;
use App\Entity\ChronicleTag;
use App\Enum\ChronicleBlockType;
use App\Enum\ChronicleStatus;
use App\Repository\ChronicleEraRepository;
use App\Repository\ChronicleSeriesRepository;
use App\Repository\ChronicleTagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

final class ChronicleEntryService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ChronicleEraRepository $eras,
        private readonly ChronicleSeriesRepository $series,
        private readonly ChronicleTagRepository $tags,
        private readonly ChronicleHashGenerator $hashGenerator,
        private readonly ChronicleMarkdownRenderer $markdown,
        private readonly SluggerInterface $slugger,
    ) {
    }

    public function createDraft(): ChronicleEntry
    {
        $entry = new ChronicleEntry();
        $entry->setShortHash($this->hashGenerator->generateUnique());
        $entry->setSlug('draft-'.$entry->getShortHash());
        $entry->setTitle('Новая запись');
        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function applyPayload(ChronicleEntry $entry, array $payload, bool $touchUpdatedAt = true): void
    {
        if (isset($payload['title']) && \is_string($payload['title'])) {
            $entry->setTitle(trim($payload['title']));
        }

        if (isset($payload['slug']) && \is_string($payload['slug']) && '' !== trim($payload['slug'])) {
            $entry->setSlug($this->slugify($payload['slug']));
        } elseif (isset($payload['title']) && \is_string($payload['title']) && str_starts_with($entry->getSlug(), 'draft-')) {
            $entry->setSlug($this->uniqueSlug($payload['title'], $entry->getId()));
        }

        if (\array_key_exists('lede', $payload)) {
            $entry->setLede(\is_string($payload['lede']) ? $payload['lede'] : null);
        }

        if (\array_key_exists('excerpt', $payload)) {
            $entry->setExcerpt(\is_string($payload['excerpt']) ? $payload['excerpt'] : null);
        }

        if (\array_key_exists('coverImagePath', $payload)) {
            $entry->setCoverImagePath(\is_string($payload['coverImagePath']) && '' !== $payload['coverImagePath']
                ? basename($payload['coverImagePath'])
                : null);
        }

        if (\array_key_exists('eraId', $payload)) {
            $era = null;
            if (null !== $payload['eraId'] && '' !== $payload['eraId']) {
                $era = $this->eras->find((int) $payload['eraId']);
            }
            $entry->setEra($era);
        }

        if (\array_key_exists('seriesId', $payload)) {
            $series = null;
            if (null !== $payload['seriesId'] && '' !== $payload['seriesId']) {
                $series = $this->series->find((int) $payload['seriesId']);
            }
            $entry->setSeries($series);
        }

        if (isset($payload['tagIds']) && \is_array($payload['tagIds'])) {
            $entry->getTags()->clear();
            foreach ($payload['tagIds'] as $tagId) {
                $tag = $this->tags->find((int) $tagId);
                if ($tag instanceof ChronicleTag) {
                    $entry->addTag($tag);
                }
            }
        }

        if (isset($payload['status']) && \is_string($payload['status'])) {
            $status = ChronicleStatus::tryFrom($payload['status']);
            if (null !== $status) {
                $entry->setStatus($status);
            }
        }

        if (\array_key_exists('publishedAt', $payload)) {
            $entry->setPublishedAt($this->parseDateTime($payload['publishedAt']));
        }

        if (\array_key_exists('isFeatured', $payload)) {
            $entry->setIsFeatured((bool) $payload['isFeatured']);
        }

        if (\array_key_exists('isUnlisted', $payload)) {
            $entry->setIsUnlisted((bool) $payload['isUnlisted']);
        }

        if (\array_key_exists('seoTitle', $payload)) {
            $entry->setSeoTitle(\is_string($payload['seoTitle']) ? $payload['seoTitle'] : null);
        }

        if (\array_key_exists('seoDescription', $payload)) {
            $entry->setSeoDescription(\is_string($payload['seoDescription']) ? $payload['seoDescription'] : null);
        }

        if (\array_key_exists('ogImagePath', $payload)) {
            $entry->setOgImagePath(\is_string($payload['ogImagePath']) && '' !== $payload['ogImagePath']
                ? basename($payload['ogImagePath'])
                : null);
        }

        if (isset($payload['blocks']) && \is_array($payload['blocks'])) {
            $this->syncBlocks($entry, $payload['blocks']);
        }

        $entry->setReadingTimeMin($this->markdown->estimateReadingMinutes($entry));

        if ($touchUpdatedAt) {
            $entry->setUpdatedAt(new \DateTimeImmutable());
        }

        $this->em->flush();
    }

    /**
     * @param list<array<string, mixed>> $blocksPayload
     */
    private function syncBlocks(ChronicleEntry $entry, array $blocksPayload): void
    {
        $existing = [];
        foreach ($entry->getBlocks() as $block) {
            if (null !== $block->getId()) {
                $existing[$block->getId()] = $block;
            }
        }

        $keptIds = [];
        foreach ($blocksPayload as $index => $row) {
            if (!\is_array($row)) {
                continue;
            }

            $block = null;
            if (isset($row['id']) && is_numeric($row['id']) && isset($existing[(int) $row['id']])) {
                $block = $existing[(int) $row['id']];
                $keptIds[] = (int) $row['id'];
            } else {
                $block = new ChronicleBlock();
                $entry->addBlock($block);
            }

            $type = ChronicleBlockType::tryFrom((string) ($row['type'] ?? 'paragraph')) ?? ChronicleBlockType::Paragraph;
            $block->setType($type);
            $block->setSortOrder(isset($row['sortOrder']) ? (int) $row['sortOrder'] : $index * 10);
            $block->setBody(isset($row['body']) && \is_string($row['body']) ? $row['body'] : null);
            $block->setHeadingLevel(isset($row['headingLevel']) ? (int) $row['headingLevel'] : 2);
            $block->setImagePath(isset($row['imagePath']) && \is_string($row['imagePath']) && '' !== $row['imagePath']
                ? basename($row['imagePath'])
                : null);
            $block->setCaption(isset($row['caption']) && \is_string($row['caption']) ? $row['caption'] : null);
            $block->setAlt(isset($row['alt']) && \is_string($row['alt']) ? $row['alt'] : null);
            $block->setOmTrackSlug(isset($row['omTrackSlug']) && \is_string($row['omTrackSlug']) ? $row['omTrackSlug'] : null);
            $block->setVideoUrl(isset($row['videoUrl']) && \is_string($row['videoUrl']) ? $row['videoUrl'] : null);
            $block->setVideoTitle(isset($row['videoTitle']) && \is_string($row['videoTitle']) ? $row['videoTitle'] : null);
            $block->setAuthor(isset($row['author']) && \is_string($row['author']) ? $row['author'] : null);
            $block->setCalloutStyle(isset($row['calloutStyle']) && \is_string($row['calloutStyle']) ? $row['calloutStyle'] : null);

            if (ChronicleBlockType::Gallery === $type && isset($row['images']) && \is_array($row['images'])) {
                $this->syncGalleryImages($block, $row['images']);
            } elseif (ChronicleBlockType::Gallery !== $type) {
                foreach ($block->getImages()->toArray() as $image) {
                    $block->removeImage($image);
                }
            }
        }

        foreach ($existing as $id => $block) {
            if (!\in_array($id, $keptIds, true)) {
                $entry->removeBlock($block);
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $imagesPayload
     */
    private function syncGalleryImages(ChronicleBlock $block, array $imagesPayload): void
    {
        $existing = [];
        foreach ($block->getImages() as $image) {
            if (null !== $image->getId()) {
                $existing[$image->getId()] = $image;
            }
        }

        $keptIds = [];
        foreach ($imagesPayload as $index => $row) {
            if (!\is_array($row)) {
                continue;
            }

            $image = null;
            if (isset($row['id']) && is_numeric($row['id']) && isset($existing[(int) $row['id']])) {
                $image = $existing[(int) $row['id']];
                $keptIds[] = (int) $row['id'];
            } else {
                $image = new ChronicleBlockImage();
                $block->addImage($image);
            }

            if (!isset($row['imagePath']) || !\is_string($row['imagePath']) || '' === $row['imagePath']) {
                continue;
            }

            $image->setImagePath(basename($row['imagePath']));
            $image->setCaption(isset($row['caption']) && \is_string($row['caption']) ? $row['caption'] : null);
            $image->setAlt(isset($row['alt']) && \is_string($row['alt']) ? $row['alt'] : null);
            $image->setSortOrder(isset($row['sortOrder']) ? (int) $row['sortOrder'] : $index * 10);
        }

        foreach ($existing as $id => $image) {
            if (!\in_array($id, $keptIds, true)) {
                $block->removeImage($image);
            }
        }
    }

    private function slugify(string $value): string
    {
        return strtolower((string) $this->slugger->slug($value));
    }

    private function uniqueSlug(string $title, ?int $excludeId): string
    {
        $base = $this->slugify($title);
        if ('' === $base) {
            $base = 'zapis';
        }

        $slug = $base;
        $i = 2;
        while ($this->slugExists($slug, $excludeId)) {
            $slug = $base.'-'.$i;
            ++$i;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $excludeId): bool
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from(ChronicleEntry::class, 'e')
            ->andWhere('e.slug = :slug')
            ->setParameter('slug', $slug);

        if (null !== $excludeId) {
            $qb->andWhere('e.id != :id')->setParameter('id', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    private function parseDateTime(mixed $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (\is_string($value)) {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Exception) {
                return null;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(ChronicleEntry $entry): array
    {
        $blocks = [];
        foreach ($entry->getBlocks() as $block) {
            $images = [];
            foreach ($block->getImages() as $image) {
                $images[] = [
                    'id' => $image->getId(),
                    'imagePath' => $image->getImagePath(),
                    'caption' => $image->getCaption(),
                    'alt' => $image->getAlt(),
                    'sortOrder' => $image->getSortOrder(),
                ];
            }

            $blocks[] = [
                'id' => $block->getId(),
                'type' => $block->getType()->value,
                'sortOrder' => $block->getSortOrder(),
                'body' => $block->getBody(),
                'headingLevel' => $block->getHeadingLevel(),
                'imagePath' => $block->getImagePath(),
                'caption' => $block->getCaption(),
                'alt' => $block->getAlt(),
                'omTrackSlug' => $block->getOmTrackSlug(),
                'videoUrl' => $block->getVideoUrl(),
                'videoTitle' => $block->getVideoTitle(),
                'author' => $block->getAuthor(),
                'calloutStyle' => $block->getCalloutStyle(),
                'images' => $images,
            ];
        }

        return [
            'id' => $entry->getId(),
            'title' => $entry->getTitle(),
            'slug' => $entry->getSlug(),
            'shortHash' => $entry->getShortHash(),
            'lede' => $entry->getLede(),
            'excerpt' => $entry->getExcerpt(),
            'coverImagePath' => $entry->getCoverImagePath(),
            'eraId' => $entry->getEra()?->getId(),
            'seriesId' => $entry->getSeries()?->getId(),
            'tagIds' => array_map(static fn (ChronicleTag $tag): int => (int) $tag->getId(), $entry->getTags()->toArray()),
            'status' => $entry->getStatus()->value,
            'publishedAt' => $entry->getPublishedAt()?->format('Y-m-d\TH:i'),
            'isFeatured' => $entry->isFeatured(),
            'isUnlisted' => $entry->isUnlisted(),
            'readingTimeMin' => $entry->getReadingTimeMin(),
            'seoTitle' => $entry->getSeoTitle(),
            'seoDescription' => $entry->getSeoDescription(),
            'ogImagePath' => $entry->getOgImagePath(),
            'previewToken' => $entry->getPreviewToken(),
            'updatedAt' => $entry->getUpdatedAt()->format('c'),
            'blocks' => $blocks,
        ];
    }

    public function createTag(string $name): ChronicleTag
    {
        $name = trim($name);
        if ('' === $name) {
            throw new \InvalidArgumentException('Tag name is required.');
        }

        $slug = $this->uniqueTagSlug($name);
        $existing = $this->tags->findOneBy(['slug' => $slug]);
        if ($existing instanceof ChronicleTag) {
            return $existing;
        }

        $tag = new ChronicleTag();
        $tag->setName($name);
        $tag->setSlug($slug);
        $this->em->persist($tag);
        $this->em->flush();

        return $tag;
    }

    private function uniqueTagSlug(string $name): string
    {
        $base = $this->slugify($name);
        if ('' === $base) {
            $base = 'tag';
        }

        $slug = $base;
        $i = 2;
        while ($this->tags->findOneBy(['slug' => $slug]) instanceof ChronicleTag) {
            $slug = $base.'-'.$i;
            ++$i;
        }

        return $slug;
    }
}
