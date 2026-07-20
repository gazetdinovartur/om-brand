<?php

namespace App\Entity;

use App\Repository\ChronicleEraRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChronicleEraRepository::class)]
#[ORM\Table(name: 'chronicle_era')]
class ChronicleEra
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $title = '';

    #[ORM\Column(length: 80, unique: true)]
    private string $slug = '';

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $periodLabel = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $color = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private int $sortOrder = 0;

    /** @var Collection<int, ChronicleEntry> */
    #[ORM\OneToMany(targetEntity: ChronicleEntry::class, mappedBy: 'era')]
    private Collection $entries;

    public function __construct()
    {
        $this->entries = new ArrayCollection();
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

    public function getPeriodLabel(): ?string
    {
        return $this->periodLabel;
    }

    public function setPeriodLabel(?string $periodLabel): static
    {
        $this->periodLabel = $periodLabel;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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

    /** @return Collection<int, ChronicleEntry> */
    public function getEntries(): Collection
    {
        return $this->entries;
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
