<?php

namespace App\Entity;

use App\Enum\CasePresentationMode;
use App\Repository\CaseStudyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CaseStudyRepository::class)]
#[ORM\Table(name: 'case_study')]
class CaseStudy
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(length: 120, unique: true)]
    private string $slug = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    /** @deprecated Use story fields instead */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverImagePath = null;

    #[ORM\Column]
    private bool $isPublished = false;

    #[ORM\Column]
    private bool $showOnLanding = false;

    #[ORM\Column]
    private bool $isFeatured = false;

    #[ORM\Column]
    private int $likeCount = 0;

    #[ORM\Column]
    private bool $hasDetailPage = false;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $domain = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $role = null;

    #[ORM\Column(nullable: true)]
    private ?int $year = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $outcomeLine = null;

    #[ORM\Column(length: 240, nullable: true)]
    private ?string $storyHook = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $storyContext = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $storyTurningPoint = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $storyApproach = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $storyBody = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $storyOutcome = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $storyReflection = null;

    #[ORM\Column(length: 280, nullable: true)]
    private ?string $quote = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $quoteAuthor = null;

    #[ORM\Column(length: 32, enumType: CasePresentationMode::class)]
    private CasePresentationMode $presentationMode = CasePresentationMode::None;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $videoUrl = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $videoTitle = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $omTrackSlug = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $audioPath = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $audioUrl = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $audioTitle = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $presentationDuration = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $presentationIntro = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $seoTitle = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $seoDescription = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ogImagePath = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, CaseStudyImage> */
    #[ORM\OneToMany(targetEntity: CaseStudyImage::class, mappedBy: 'caseStudy', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'id' => 'ASC'])]
    private Collection $galleryImages;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->galleryImages = new ArrayCollection();
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

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): static
    {
        $this->summary = $summary;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;

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

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;

        return $this;
    }

    public function isShowOnLanding(): bool
    {
        return $this->showOnLanding;
    }

    public function setShowOnLanding(bool $showOnLanding): static
    {
        $this->showOnLanding = $showOnLanding;

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

    public function getLikeCount(): int
    {
        return $this->likeCount;
    }

    public function setLikeCount(int $likeCount): static
    {
        $this->likeCount = max(0, $likeCount);

        return $this;
    }

    public function hasDetailPage(): bool
    {
        return $this->hasDetailPage;
    }

    public function setHasDetailPage(bool $hasDetailPage): static
    {
        $this->hasDetailPage = $hasDetailPage;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function setDomain(?string $domain): static
    {
        $this->domain = $domain;

        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(?int $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getOutcomeLine(): ?string
    {
        return $this->outcomeLine;
    }

    public function setOutcomeLine(?string $outcomeLine): static
    {
        $this->outcomeLine = $outcomeLine;

        return $this;
    }

    public function getStoryHook(): ?string
    {
        return $this->storyHook;
    }

    public function setStoryHook(?string $storyHook): static
    {
        $this->storyHook = $storyHook;

        return $this;
    }

    public function getStoryContext(): ?string
    {
        return $this->storyContext;
    }

    public function setStoryContext(?string $storyContext): static
    {
        $this->storyContext = $storyContext;

        return $this;
    }

    public function getStoryTurningPoint(): ?string
    {
        return $this->storyTurningPoint;
    }

    public function setStoryTurningPoint(?string $storyTurningPoint): static
    {
        $this->storyTurningPoint = $storyTurningPoint;

        return $this;
    }

    public function getStoryApproach(): ?string
    {
        return $this->storyApproach;
    }

    public function setStoryApproach(?string $storyApproach): static
    {
        $this->storyApproach = $storyApproach;

        return $this;
    }

    public function getStoryBody(): ?string
    {
        return $this->storyBody;
    }

    public function setStoryBody(?string $storyBody): static
    {
        $this->storyBody = $storyBody;

        return $this;
    }

    public function getStoryOutcome(): ?string
    {
        return $this->storyOutcome;
    }

    public function setStoryOutcome(?string $storyOutcome): static
    {
        $this->storyOutcome = $storyOutcome;

        return $this;
    }

    public function getStoryReflection(): ?string
    {
        return $this->storyReflection;
    }

    public function setStoryReflection(?string $storyReflection): static
    {
        $this->storyReflection = $storyReflection;

        return $this;
    }

    public function getQuote(): ?string
    {
        return $this->quote;
    }

    public function setQuote(?string $quote): static
    {
        $this->quote = $quote;

        return $this;
    }

    public function getQuoteAuthor(): ?string
    {
        return $this->quoteAuthor;
    }

    public function setQuoteAuthor(?string $quoteAuthor): static
    {
        $this->quoteAuthor = $quoteAuthor;

        return $this;
    }

    public function getPresentationMode(): CasePresentationMode
    {
        return $this->presentationMode;
    }

    public function setPresentationMode(CasePresentationMode $presentationMode): static
    {
        $this->presentationMode = $presentationMode;

        return $this;
    }

    public function getVideoUrl(): ?string
    {
        return $this->videoUrl;
    }

    public function setVideoUrl(?string $videoUrl): static
    {
        $this->videoUrl = $videoUrl;

        return $this;
    }

    public function getVideoTitle(): ?string
    {
        return $this->videoTitle;
    }

    public function setVideoTitle(?string $videoTitle): static
    {
        $this->videoTitle = $videoTitle;

        return $this;
    }

    public function getOmTrackSlug(): ?string
    {
        return $this->omTrackSlug;
    }

    public function setOmTrackSlug(?string $omTrackSlug): static
    {
        $this->omTrackSlug = $omTrackSlug;

        return $this;
    }

    public function getAudioPath(): ?string
    {
        return $this->audioPath;
    }

    public function setAudioPath(?string $audioPath): static
    {
        $this->audioPath = $audioPath;

        return $this;
    }

    public function getAudioUrl(): ?string
    {
        return $this->audioUrl;
    }

    public function setAudioUrl(?string $audioUrl): static
    {
        $this->audioUrl = $audioUrl;

        return $this;
    }

    public function getAudioTitle(): ?string
    {
        return $this->audioTitle;
    }

    public function setAudioTitle(?string $audioTitle): static
    {
        $this->audioTitle = $audioTitle;

        return $this;
    }

    public function getPresentationDuration(): ?string
    {
        return $this->presentationDuration;
    }

    public function setPresentationDuration(?string $presentationDuration): static
    {
        $this->presentationDuration = $presentationDuration;

        return $this;
    }

    public function getPresentationIntro(): ?string
    {
        return $this->presentationIntro;
    }

    public function setPresentationIntro(?string $presentationIntro): static
    {
        $this->presentationIntro = $presentationIntro;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, CaseStudyImage> */
    public function getGalleryImages(): Collection
    {
        return $this->galleryImages;
    }

    public function addGalleryImage(CaseStudyImage $galleryImage): static
    {
        if (!$this->galleryImages->contains($galleryImage)) {
            $this->galleryImages->add($galleryImage);
            $galleryImage->setCaseStudy($this);
        }

        return $this;
    }

    public function removeGalleryImage(CaseStudyImage $galleryImage): static
    {
        if ($this->galleryImages->removeElement($galleryImage) && $galleryImage->getCaseStudy() === $this) {
            $galleryImage->setCaseStudy(null);
        }

        return $this;
    }

    public function hasStoryContent(): bool
    {
        return $this->isFilled($this->storyBody)
            || ($this->isFilled($this->storyHook) && $this->isFilled($this->storyOutcome));
    }

    public function isDetailPublic(): bool
    {
        return $this->isPublished && $this->hasDetailPage && $this->hasStoryContent();
    }

    public function hasVideoPresentation(): bool
    {
        return $this->presentationMode->wantsVideo() && $this->isFilled($this->videoUrl);
    }

    public function hasAudioPresentation(): bool
    {
        if (!$this->presentationMode->wantsAudio()) {
            return false;
        }

        return $this->isFilled($this->omTrackSlug)
            || $this->isFilled($this->audioPath)
            || $this->isFilled($this->audioUrl);
    }

    public function hasPresentation(): bool
    {
        return $this->hasVideoPresentation() || $this->hasAudioPresentation();
    }

    public function prefersOmPlayer(): bool
    {
        return $this->isFilled($this->omTrackSlug);
    }

    public function resolveStoryBody(): ?string
    {
        if ($this->isFilled($this->storyBody)) {
            return $this->storyBody;
        }

        // Fallback: older long-form fields folded into one history block.
        $legacy = array_filter([
            $this->storyContext,
            $this->storyTurningPoint,
            $this->storyApproach,
            $this->content,
        ], fn (?string $part): bool => $this->isFilled($part));

        if ([] === $legacy) {
            return null;
        }

        return implode("\n\n", array_map(static fn (string $part): string => trim($part), $legacy));
    }

    public function resolveStoryOutcome(): ?string
    {
        if ($this->isFilled($this->storyOutcome) && $this->isFilled($this->storyReflection)) {
            return trim((string) $this->storyOutcome)."\n\n".trim((string) $this->storyReflection);
        }

        if ($this->isFilled($this->storyOutcome)) {
            return trim((string) $this->storyOutcome);
        }

        return $this->isFilled($this->storyReflection) ? trim((string) $this->storyReflection) : null;
    }

    /**
     * Three story beats for the detail page.
     *
     * @return list<array{label: string, text: string}>
     */
    public function storySections(): array
    {
        $sections = [];

        if ($this->isFilled($this->storyHook)) {
            $sections[] = ['label' => 'Вступление', 'text' => trim((string) $this->storyHook)];
        }

        $body = $this->resolveStoryBody();
        if ($this->isFilled($body)) {
            $sections[] = ['label' => 'История', 'text' => trim((string) $body)];
        }

        $after = $this->resolveStoryOutcome();
        if ($this->isFilled($after)) {
            $sections[] = ['label' => 'Итог', 'text' => trim((string) $after)];
        }

        return $sections;
    }

    public function resolveTeaser(): ?string
    {
        if ($this->isFilled($this->outcomeLine)) {
            return $this->outcomeLine;
        }

        return $this->summary;
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

        if ($this->isFilled($this->outcomeLine)) {
            return (string) $this->outcomeLine;
        }

        if ($this->isFilled($this->summary)) {
            return (string) $this->summary;
        }

        if ($this->isFilled($this->storyHook)) {
            return (string) $this->storyHook;
        }

        return $this->title;
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
