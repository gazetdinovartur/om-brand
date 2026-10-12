<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReadingProgressRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReadingProgressRepository::class)]
#[ORM\Table(
    name: 'reading_progress',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_reading_progress_user_book', columns: ['site_user_id', 'book_id']),
    ],
)]
#[ORM\HasLifecycleCallbacks]
class ReadingProgress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SiteUser::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?SiteUser $siteUser = null;

    #[ORM\ManyToOne(targetEntity: LibraryBook::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?LibraryBook $book = null;

    #[ORM\ManyToOne(targetEntity: LibraryChapter::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?LibraryChapter $chapter = null;

    #[ORM\Column(length: 64)]
    private string $blockId = '';

    /** 0–10000, where 10000 is 100%. */
    #[ORM\Column]
    private int $progressBasisPoints = 0;

    #[ORM\Column]
    private \DateTimeImmutable $lastOpenedAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->lastOpenedAt = $now;
        $this->updatedAt = $now;
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

    public function getSiteUser(): ?SiteUser
    {
        return $this->siteUser;
    }

    public function setSiteUser(SiteUser $siteUser): static
    {
        $this->siteUser = $siteUser;

        return $this;
    }

    public function getBook(): ?LibraryBook
    {
        return $this->book;
    }

    public function setBook(LibraryBook $book): static
    {
        $this->book = $book;

        return $this;
    }

    public function getChapter(): ?LibraryChapter
    {
        return $this->chapter;
    }

    public function setChapter(LibraryChapter $chapter): static
    {
        $this->chapter = $chapter;

        return $this;
    }

    public function getBlockId(): string
    {
        return $this->blockId;
    }

    public function setBlockId(string $blockId): static
    {
        $this->blockId = $blockId;

        return $this;
    }

    public function getProgressBasisPoints(): int
    {
        return $this->progressBasisPoints;
    }

    public function setProgressBasisPoints(int $progressBasisPoints): static
    {
        $this->progressBasisPoints = min(10000, max(0, $progressBasisPoints));

        return $this;
    }

    public function getProgressPercent(): float
    {
        return round($this->progressBasisPoints / 100, 1);
    }

    public function getLastOpenedAt(): \DateTimeImmutable
    {
        return $this->lastOpenedAt;
    }

    public function markOpened(?\DateTimeImmutable $at = null): static
    {
        $this->lastOpenedAt = $at ?? new \DateTimeImmutable();

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
