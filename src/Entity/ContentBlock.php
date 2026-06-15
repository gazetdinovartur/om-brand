<?php

namespace App\Entity;

use App\Enum\ContentBlockType;
use App\Repository\ContentBlockRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContentBlockRepository::class)]
#[ORM\Table(name: 'content_block')]
class ContentBlock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $slug = '';

    #[ORM\Column(enumType: ContentBlockType::class)]
    private ContentBlockType $type = ContentBlockType::Text;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $subtitle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $body = null;

    /** @var array<int, array<string, string>>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $items = null;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column]
    private bool $isVisible = true;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getType(): ContentBlockType
    {
        return $this->type;
    }

    public function setType(ContentBlockType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): static
    {
        $this->subtitle = $subtitle;

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

    /** @return array<int, array<string, string>> */
    public function getItems(): array
    {
        return $this->items ?? [];
    }

    /** @param array<int, array<string, string>>|null $items */
    public function setItems(?array $items): static
    {
        if (null === $items) {
            $this->items = null;

            return $this;
        }

        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $text = trim((string) ($item['text'] ?? ''));
            if ('' === $text) {
                continue;
            }

            $entry = ['text' => $text];
            $title = trim((string) ($item['title'] ?? ''));
            if ('' !== $title) {
                $entry['title'] = $title;
            }

            $normalized[] = $entry;
        }

        $this->items = [] === $normalized ? null : $normalized;

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

    public function isVisible(): bool
    {
        return $this->isVisible;
    }

    public function setIsVisible(bool $isVisible): static
    {
        $this->isVisible = $isVisible;

        return $this;
    }

    public function __toString(): string
    {
        return $this->slug;
    }
}
