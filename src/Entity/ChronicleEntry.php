<?php

namespace App\Entity;

use App\Enum\ChronicleStatus;
use App\Repository\ChronicleEntryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChronicleEntryRepository::class)]
#[ORM\Table(name: 'chronicle_entry')]
#[ORM\HasLifecycleCallbacks]
class ChronicleEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(length: 120, unique: true)]
    private string $slug = '';

    #[ORM\Column(length: 8, unique: true)]
    private string $shortHash = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lede = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $excerpt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverImagePath = null;

    #[ORM\ManyToOne(targetEntity: ChronicleEra::class, inversedBy: 'entries')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ChronicleEra $era = null;

    #[ORM\ManyToOne(targetEntity: ChronicleSeries::class, inversedBy: 'entries')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ChronicleSeries $series = null;

    /** @var Collection<int, ChronicleTag> */
    #[ORM\ManyToMany(targetEntity: ChronicleTag::class, inversedBy: 'entries')]
    #[ORM\JoinTable(name: 'chronicle_entry_tag')]
    private Collection $tags;

    #[ORM\Column(length: 16, enumType: ChronicleStatus::class)]
    private ChronicleStatus $status = ChronicleStatus::Draft;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column]
    private int $readingTimeMin = 1;

    #[ORM\Column]
    private bool $isFeatured = false;

    #[ORM\Column]
    private bool $isUnlisted = false;

    #[ORM\Column(length: 64)]
    private string $previewToken = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $seoTitle = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $seoDescription = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ogImagePath = null;

    /** @var Collection<int, ChronicleBlock> */
    #[ORM\OneToMany(targetEntity: ChronicleBlock::class, mappedBy: 'entry', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'id' => 'ASC'])]
    private Collection $blocks;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->tags = new ArrayCollection();
        $this->blocks = new ArrayCollection();
        $this->previewToken = bin2hex(random_bytes(16));
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getShortHash(): string
    {
        return $this->shortHash;
    }

    public function setShortHash(string $shortHash): static
    {
        $this->shortHash = $shortHash;

        return $this;
    }

    public function getLede(): ?string
    {
        return $this->lede;
    }

    public function setLede(?string $lede): static
    {
        $this->lede = $lede;

        return $this;
    }

    public function getExcerpt(): ?string
    {
        return $this->excerpt;
    }

    public function setExcerpt(?string $excerpt): static
    {
        $this->excerpt = $excerpt;

        return $this;
    }

    public function getCoverImagePath(): ?string
    {
        return $this->coverImagePath;
    }

    public function setCoverImagePath(?string $coverImagePath): static
    {
        $this->coverImagePath = $coverImagePath;

        return $this;
    }

    public function getEra(): ?ChronicleEra
    {
        return $this->era;
    }

    public function setEra(?ChronicleEra $era): static
    {
        $this->era = $era;

        return $this;
    }

    public function getSeries(): ?ChronicleSeries
    {
        return $this->series;
    }

    public function setSeries(?ChronicleSeries $series): static
    {
        $this->series = $series;

        return $this;
    }

    /** @return Collection<int, ChronicleTag> */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(ChronicleTag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(ChronicleTag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    public function getStatus(): ChronicleStatus
    {
        return $this->status;
    }

    public function setStatus(ChronicleStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getReadingTimeMin(): int
    {
        return $this->readingTimeMin;
    }

    public function setReadingTimeMin(int $readingTimeMin): static
    {
        $this->readingTimeMin = max(1, $readingTimeMin);

        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): static
    {
        $this->isFeatured = $isFeatured;

        return $this;
    }

    public function isUnlisted(): bool
    {
        return $this->isUnlisted;
    }

    public function setIsUnlisted(bool $isUnlisted): static
    {
        $this->isUnlisted = $isUnlisted;

        return $this;
    }

    public function getPreviewToken(): string
    {
        return $this->previewToken;
    }

    public function setPreviewToken(string $previewToken): static
    {
        $this->previewToken = $previewToken;

        return $this;
    }

    public function getSeoTitle(): ?string
    {
        return $this->seoTitle;
    }

    public function setSeoTitle(?string $seoTitle): static
    {
        $this->seoTitle = $seoTitle;

        return $this;
    }

    public function getSeoDescription(): ?string
    {
        return $this->seoDescription;
    }

    public function setSeoDescription(?string $seoDescription): static
    {
        $this->seoDescription = $seoDescription;

        return $this;
    }

    public function getOgImagePath(): ?string
    {
        return $this->ogImagePath;
    }

    public function setOgImagePath(?string $ogImagePath): static
    {
        $this->ogImagePath = $ogImagePath;

        return $this;
    }

    /** @return Collection<int, ChronicleBlock> */
    public function getBlocks(): Collection
    {
        return $this->blocks;
    }

    public function addBlock(ChronicleBlock $block): static
    {
        if (!$this->blocks->contains($block)) {
            $this->blocks->add($block);
            $block->setEntry($this);
        }

        return $this;
    }

    public function removeBlock(ChronicleBlock $block): static
    {
        if ($this->blocks->removeElement($block) && $block->getEntry() === $this) {
            $block->setEntry(null);
        }

        return $this;
    }

    public function isPublic(): bool
    {
        if (ChronicleStatus::Published !== $this->status) {
            return false;
        }

        if (null === $this->publishedAt) {
            return false;
        }

        return $this->publishedAt <= new \DateTimeImmutable();
    }

    public function isVisibleInFeed(): bool
    {
        return $this->isPublic() && !$this->isUnlisted;
    }

    public function wasUpdatedAfterPublish(): bool
    {
        if (null === $this->publishedAt) {
            return false;
        }

        return $this->updatedAt > $this->publishedAt->modify('+1 minute');
    }

    public function resolveTeaser(): string
    {
        if ($this->isFilled($this->lede)) {
            return (string) $this->lede;
        }

        if ($this->isFilled($this->excerpt)) {
            return (string) $this->excerpt;
        }

        return $this->title;
    }

    public function resolveSeoTitle(string $brandName): string
    {
        if ($this->isFilled($this->seoTitle)) {
            return (string) $this->seoTitle;
        }

        return sprintf('%s · %s', $this->title, $brandName);
    }

    public function resolveSeoDescription(): string
    {
        if ($this->isFilled($this->seoDescription)) {
            return (string) $this->seoDescription;
        }

        if ($this->isFilled($this->excerpt)) {
            return (string) $this->excerpt;
        }

        if ($this->isFilled($this->lede)) {
            return (string) $this->lede;
        }

        return $this->title;
    }

    /**
     * @return list<array{id: string, label: string, level: int}>
     */
    public function tableOfContents(): array
    {
        $items = [];
        foreach ($this->blocks as $block) {
            $anchor = $block->anchorId();
            if (null !== $anchor) {
                $items[] = [
                    'id' => $anchor,
                    'label' => trim((string) $block->getBody()),
                    'level' => $block->getHeadingLevel(),
                ];
            }
        }

        return $items;
    }

    private function isFilled(?string $value): bool
    {
        return null !== $value && '' !== trim($value);
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
