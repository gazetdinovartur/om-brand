<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReadingNoteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReadingNoteRepository::class)]
#[ORM\Table(name: 'reading_note')]
#[ORM\Index(name: 'idx_reading_note_user_book', columns: ['site_user_id', 'book_id'])]
class ReadingNote
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

    #[ORM\Column(type: Types::TEXT)]
    private string $body = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
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

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = trim($body);
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
