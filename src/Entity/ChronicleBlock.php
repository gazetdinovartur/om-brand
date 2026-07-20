<?php

namespace App\Entity;

use App\Enum\ChronicleBlockType;
use App\Repository\ChronicleBlockRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChronicleBlockRepository::class)]
#[ORM\Table(name: 'chronicle_block')]
class ChronicleBlock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ChronicleEntry::class, inversedBy: 'blocks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ChronicleEntry $entry = null;

    #[ORM\Column(length: 32, enumType: ChronicleBlockType::class)]
    private ChronicleBlockType $type = ChronicleBlockType::Paragraph;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $body = null;

    #[ORM\Column]
    private int $headingLevel = 2;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(length: 240, nullable: true)]
    private ?string $caption = null;

    #[ORM\Column(length: 240, nullable: true)]
    private ?string $alt = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $omTrackSlug = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $videoUrl = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $videoTitle = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $author = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $calloutStyle = null;

    /** @var Collection<int, ChronicleBlockImage> */
    #[ORM\OneToMany(targetEntity: ChronicleBlockImage::class, mappedBy: 'block', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'id' => 'ASC'])]
    private Collection $images;

    public function __construct()
    {
        $this->images = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntry(): ?ChronicleEntry
    {
        return $this->entry;
    }

    public function setEntry(?ChronicleEntry $entry): static
    {
        $this->entry = $entry;

        return $this;
    }

    public function getType(): ChronicleBlockType
    {
        return $this->type;
    }

    public function setType(ChronicleBlockType $type): static
    {
        $this->type = $type;

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

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function getHeadingLevel(): int
    {
        return $this->headingLevel;
    }

    public function setHeadingLevel(int $headingLevel): static
    {
        $this->headingLevel = max(2, min(3, $headingLevel));

        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): static
    {
        $this->imagePath = $imagePath;

        return $this;
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function setCaption(?string $caption): static
    {
        $this->caption = $caption;

        return $this;
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(?string $alt): static
    {
        $this->alt = $alt;

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

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(?string $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getCalloutStyle(): ?string
    {
        return $this->calloutStyle;
    }

    public function setCalloutStyle(?string $calloutStyle): static
    {
        $this->calloutStyle = $calloutStyle;

        return $this;
    }

    /** @return Collection<int, ChronicleBlockImage> */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(ChronicleBlockImage $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setBlock($this);
        }

        return $this;
    }

    public function removeImage(ChronicleBlockImage $image): static
    {
        if ($this->images->removeElement($image) && $image->getBlock() === $this) {
            $image->setBlock(null);
        }

        return $this;
    }

    public function anchorId(): ?string
    {
        if (ChronicleBlockType::Heading !== $this->type || !$this->isFilled($this->body)) {
            return null;
        }

        $slug = mb_strtolower(trim((string) $this->body));
        $slug = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $slug) ?? '';
        $slug = preg_replace('/[\s-]+/u', '-', $slug) ?? '';

        return '' !== $slug ? $slug : null;
    }

    private function isFilled(?string $value): bool
    {
        return null !== $value && '' !== trim($value);
    }
}
