<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LibraryBookRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LibraryBookRepository::class)]
#[ORM\Table(name: 'library_book')]
#[ORM\HasLifecycleCallbacks]
class LibraryBook
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(length: 180)]
    private string $author = '';

    #[ORM\Column(length: 120, unique: true)]
    private string $slug = '';

    #[ORM\Column(length: 8)]
    private string $language = 'ru';

    #[ORM\Column(length: 64)]
    private string $sourceChecksum = '';

    #[ORM\Column]
    private int $totalCharacters = 0;

    /** html = parsed text reader; pdf_fallback = original PDF viewer */
    #[ORM\Column(length: 32)]
    private string $renderMode = 'html';

    /** Relative path under private uploads for fallback PDF source. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sourcePath = null;

    /** @var array<string, string> */
    #[ORM\Column(type: Types::JSON)]
    private array $footnotes = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, LibraryChapter> */
    #[ORM\OneToMany(
        targetEntity: LibraryChapter::class,
        mappedBy: 'book',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'id' => 'ASC'])]
    private Collection $chapters;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->chapters = new ArrayCollection();
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
        $this->title = trim($title);

        return $this;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function setAuthor(string $author): static
    {
        $this->author = trim($author);

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = trim($slug);

        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = trim($language);

        return $this;
    }

    public function getSourceChecksum(): string
    {
        return $this->sourceChecksum;
    }

    public function setSourceChecksum(string $sourceChecksum): static
    {
        $this->sourceChecksum = $sourceChecksum;

        return $this;
    }

    public function getTotalCharacters(): int
    {
        return $this->totalCharacters;
    }

    public function setTotalCharacters(int $totalCharacters): static
    {
        $this->totalCharacters = max(0, $totalCharacters);

        return $this;
    }

    public function getRenderMode(): string
    {
        return $this->renderMode;
    }

    public function setRenderMode(string $renderMode): static
    {
        $this->renderMode = trim($renderMode) ?: 'html';

        return $this;
    }

    public function isPdfFallback(): bool
    {
        return 'pdf_fallback' === $this->renderMode;
    }

    public function getSourcePath(): ?string
    {
        return $this->sourcePath;
    }

    public function setSourcePath(?string $sourcePath): static
    {
        $sourcePath = null === $sourcePath ? null : trim($sourcePath);
        $this->sourcePath = '' === $sourcePath ? null : $sourcePath;

        return $this;
    }

    /** @return array<string, string> */
    public function getFootnotes(): array
    {
        return $this->footnotes;
    }

    /** @param array<int|string, string> $footnotes */
    public function setFootnotes(array $footnotes): static
    {
        $normalized = [];
        foreach ($footnotes as $number => $text) {
            $normalized[(string) $number] = $text;
        }
        $this->footnotes = $normalized;

        return $this;
    }

    public function getFootnote(int $number): ?string
    {
        return $this->footnotes[(string) $number] ?? null;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, LibraryChapter> */
    public function getChapters(): Collection
    {
        return $this->chapters;
    }

    public function addChapter(LibraryChapter $chapter): static
    {
        if (!$this->chapters->contains($chapter)) {
            $this->chapters->add($chapter);
            $chapter->setBook($this);
        }

        return $this;
    }

    public function removeChapter(LibraryChapter $chapter): static
    {
        if ($this->chapters->removeElement($chapter) && $chapter->getBook() === $this) {
            $chapter->setBook(null);
        }

        return $this;
    }

    public function getFirstChapter(): ?LibraryChapter
    {
        $chapter = $this->chapters->first();

        return false === $chapter ? null : $chapter;
    }

    public function getChapterCount(): int
    {
        return $this->chapters->count();
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
