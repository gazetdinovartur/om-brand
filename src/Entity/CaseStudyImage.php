<?php

namespace App\Entity;

use App\Repository\CaseStudyImageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CaseStudyImageRepository::class)]
#[ORM\Table(name: 'case_study_image')]
class CaseStudyImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'galleryImages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CaseStudy $caseStudy = null;

    #[ORM\Column(length: 255)]
    private string $imagePath = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $caption = null;

    #[ORM\Column]
    private int $sortOrder = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCaseStudy(): ?CaseStudy
    {
        return $this->caseStudy;
    }

    public function setCaseStudy(?CaseStudy $caseStudy): static
    {
        $this->caseStudy = $caseStudy;

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

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function __toString(): string
    {
        return $this->caption ?: ($this->imagePath !== '' ? basename($this->imagePath) : 'Кадр');
    }
}
