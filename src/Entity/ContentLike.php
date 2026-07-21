<?php

namespace App\Entity;

use App\Enum\ContentLikeTarget;
use App\Repository\ContentLikeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContentLikeRepository::class)]
#[ORM\Table(name: 'content_like')]
#[ORM\UniqueConstraint(name: 'uniq_content_like_visitor', columns: ['target_type', 'target_id', 'visitor_token'])]
#[ORM\Index(name: 'idx_content_like_target', columns: ['target_type', 'target_id'])]
class ContentLike
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 16, enumType: ContentLikeTarget::class)]
    private ContentLikeTarget $targetType;

    #[ORM\Column]
    private int $targetId;

    #[ORM\Column(length: 64)]
    private string $visitorToken;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(ContentLikeTarget $targetType, int $targetId, string $visitorToken)
    {
        $this->targetType = $targetType;
        $this->targetId = $targetId;
        $this->visitorToken = $visitorToken;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTargetType(): ContentLikeTarget
    {
        return $this->targetType;
    }

    public function getTargetId(): int
    {
        return $this->targetId;
    }

    public function getVisitorToken(): string
    {
        return $this->visitorToken;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
