<?php

namespace App\Entity;

use App\Repository\ChronicleBlockImageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChronicleBlockImageRepository::class)]
#[ORM\Table(name: 'chronicle_block_image')]
class ChronicleBlockImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ChronicleBlock::class, inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ChronicleBlock $block = null;

    #[ORM\Column(length: 255)]
    private string $imagePath = '';

    #[ORM\Column(length: 240, nullable: true)]
    private ?string $caption = null;

    #[ORM\Column(length: 240, nullable: true)]
    private ?string $alt = null;

    #[ORM\Column]
    private int $sortOrder = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBlock(): ?ChronicleBlock
    {
        return $this->block;
    }

    public function setBlock(?ChronicleBlock $block): static
    {
        $this->block = $block;

        return $this;
    }

    public function getImagePath(): string
    {
        return $this->imagePath;
    }

    public function setImagePath(string $imagePath): static
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

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }
}
