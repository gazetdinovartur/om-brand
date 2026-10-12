<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReadingBookmarkRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReadingBookmarkRepository::class)]
#[ORM\Table(
    name: 'reading_bookmark',
    uniqueConstraints: [
        new ORM\UniqueConstraint(
            name: 'uniq_reading_bookmark_locator',
            columns: ['site_user_id', 'book_id', 'chapter_id', 'block_id'],
        ),
    ],
)]
class ReadingBookmark
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

    #[ORM\Column(length: 280)]
    private string $excerpt = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getExcerpt(): string
    {
        return $this->excerpt;
    }

    public function setExcerpt(string $excerpt): static
    {
        $this->excerpt = trim($excerpt);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
