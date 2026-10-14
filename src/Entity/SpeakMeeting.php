<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SpeakMeetingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SpeakMeetingRepository::class)]
#[ORM\Table(name: 'speak_meeting')]
#[ORM\UniqueConstraint(name: 'uniq_speak_meeting_token', columns: ['token'])]
#[ORM\Index(columns: ['kinescope_room_id'], name: 'idx_speak_meeting_room')]
class SpeakMeeting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $token;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $kinescopeRoomId = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $kinescopeRoomCode = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $speakEmbedUrl = null;

    #[ORM\Column]
    private bool $autorecording = true;

    #[ORM\Column(length: 32)]
    private string $roomType = 'permanent_meeting';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->token = Uuid::v7()->toRfc4122();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(?\DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getKinescopeRoomId(): ?string
    {
        return $this->kinescopeRoomId;
    }

    public function setKinescopeRoomId(?string $kinescopeRoomId): static
    {
        $this->kinescopeRoomId = null !== $kinescopeRoomId && '' !== trim($kinescopeRoomId)
            ? trim($kinescopeRoomId)
            : null;

        return $this;
    }

    public function getKinescopeRoomCode(): ?string
    {
        return $this->kinescopeRoomCode;
    }

    public function setKinescopeRoomCode(?string $kinescopeRoomCode): static
    {
        $this->kinescopeRoomCode = null !== $kinescopeRoomCode && '' !== trim($kinescopeRoomCode)
            ? trim($kinescopeRoomCode)
            : null;

        return $this;
    }

    public function getSpeakEmbedUrl(): ?string
    {
        if (null !== $this->speakEmbedUrl) {
            return $this->speakEmbedUrl;
        }
        if (null !== $this->kinescopeRoomCode) {
            return 'https://speak.kinescope.io/'.$this->kinescopeRoomCode;
        }

        return null;
    }

    /**
     * Relative site link for EasyAdmin UrlField (non-empty so EA does not show «Недоступно»).
     */
    public function getSiteMeetUrl(): string
    {
        return '/meet/'.$this->token;
    }

    public function setSpeakEmbedUrl(?string $speakEmbedUrl): static
    {
        $this->speakEmbedUrl = null !== $speakEmbedUrl && '' !== trim($speakEmbedUrl)
            ? trim($speakEmbedUrl)
            : null;

        return $this;
    }

    public function isAutorecording(): bool
    {
        return $this->autorecording;
    }

    public function setAutorecording(bool $autorecording): static
    {
        $this->autorecording = $autorecording;

        return $this;
    }

    public function getRoomType(): string
    {
        return $this->roomType;
    }

    public function setRoomType(string $roomType): static
    {
        $this->roomType = $roomType;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

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

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return '' !== $this->title ? $this->title : 'Встреча #'.($this->id ?? '?');
    }
}
