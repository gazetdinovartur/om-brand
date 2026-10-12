<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LibraryChapterRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LibraryChapterRepository::class)]
#[ORM\Table(
    name: 'library_chapter',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_library_chapter_book_slug', columns: ['book_id', 'slug']),
        new ORM\UniqueConstraint(name: 'uniq_library_chapter_book_order', columns: ['book_id', 'sort_order']),
    ],
)]
class LibraryChapter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LibraryBook::class, inversedBy: 'chapters')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?LibraryBook $book = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(length: 120)]
    private string $slug = '';

    #[ORM\Column]
    private int $sortOrder = 0;

    /**
     * @var list<array{id: string, type: string, text: string, level?: int}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $blocks = [];

    #[ORM\Column]
    private int $characterCount = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBook(): ?LibraryBook
    {
        return $this->book;
    }

    public function setBook(?LibraryBook $book): static
    {
        $this->book = $book;

        return $this;
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = trim($slug);

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

    /**
     * @return list<array{id: string, type: string, text: string, level?: int}>
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    /**
     * @param list<array{id: string, type: string, text: string, level?: int}> $blocks
     */
    public function setBlocks(array $blocks): static
    {
        $this->blocks = array_values($blocks);

        return $this;
    }

    public function getCharacterCount(): int
    {
        return $this->characterCount;
    }

    public function setCharacterCount(int $characterCount): static
    {
        $this->characterCount = max(0, $characterCount);

        return $this;
    }

    /**
     * @return array{id: string, type: string, text: string, level?: int}|null
     */
    public function findBlock(string $blockId): ?array
    {
        foreach ($this->blocks as $block) {
            if ($block['id'] === $blockId) {
                return $block;
            }
        }

        return null;
    }

    public function hasBlock(string $blockId): bool
    {
        return null !== $this->findBlock($blockId);
    }

    /** @return list<int> */
    public function referencedFootnoteNumbers(): array
    {
        $numbers = [];
        foreach ($this->blocks as $block) {
            if (preg_match_all('/\\[\\[note:(\\d+)]]/', $block['text'], $matches)) {
                foreach ($matches[1] as $number) {
                    $numbers[(int) $number] = true;
                }
            }
        }

        $result = array_keys($numbers);
        sort($result, \SORT_NUMERIC);

        return $result;
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
