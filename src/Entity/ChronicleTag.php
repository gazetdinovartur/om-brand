<?php

namespace App\Entity;

use App\Repository\ChronicleTagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChronicleTagRepository::class)]
#[ORM\Table(name: 'chronicle_tag')]
class ChronicleTag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private string $name = '';

    #[ORM\Column(length: 80, unique: true)]
    private string $slug = '';

    /** @var Collection<int, ChronicleEntry> */
    #[ORM\ManyToMany(targetEntity: ChronicleEntry::class, mappedBy: 'tags')]
    private Collection $entries;

    public function __construct()
    {
        $this->entries = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    /** @return Collection<int, ChronicleEntry> */
    public function getEntries(): Collection
    {
        return $this->entries;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
