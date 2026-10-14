<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PracticeSessionKind;
use App\Enum\PracticeSessionStatus;
use App\Repository\PracticeSessionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PracticeSessionRepository::class)]
#[ORM\Table(name: 'practice_session')]
#[ORM\Index(columns: ['status'], name: 'idx_practice_session_status')]
#[ORM\Index(columns: ['kinescope_event_id'], name: 'idx_practice_session_event')]
#[ORM\Index(columns: ['kinescope_room_id'], name: 'idx_practice_session_room')]
class PracticeSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(enumType: PracticeSessionKind::class, options: ['default' => 'live'])]
    private PracticeSessionKind $kind = PracticeSessionKind::Live;

    #[ORM\Column(enumType: PracticeSessionStatus::class)]
    private PracticeSessionStatus $status = PracticeSessionStatus::Scheduled;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $kinescopeEventId = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $kinescopeVideoId = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $kinescopeRoomId = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $kinescopeRoomCode = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $kinescopeIngestUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $kinescopeStreamKey = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $kinescopeChatEmbedUrl = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $posterUrl = null;

    #[ORM\Column(nullable: true)]
    private ?int $durationSeconds = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $recordingReadyAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $debitedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->startsAt = $now;
        $this->createdAt = $now;
        $this->updatedAt = $now;
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
        $this->title = $title;

        return $this;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(\DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getKind(): PracticeSessionKind
    {
        return $this->kind;
    }

    public function setKind(PracticeSessionKind $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    public function getStatus(): PracticeSessionStatus
    {
        return $this->status;
    }

    public function setStatus(PracticeSessionStatus $status): static
    {
        $this->status = $status;
        $this->touch();

        return $this;
    }

    public function getKinescopeEventId(): ?string
    {
        return $this->kinescopeEventId;
    }

    public function setKinescopeEventId(?string $kinescopeEventId): static
    {
        $this->kinescopeEventId = null !== $kinescopeEventId && '' !== trim($kinescopeEventId)
            ? trim($kinescopeEventId)
            : null;

        return $this;
    }

    public function getKinescopeVideoId(): ?string
    {
        return $this->kinescopeVideoId;
    }

    public function setKinescopeVideoId(?string $kinescopeVideoId): static
    {
        $this->kinescopeVideoId = null !== $kinescopeVideoId && '' !== trim($kinescopeVideoId)
            ? trim($kinescopeVideoId)
            : null;

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

    public function getKinescopeIngestUrl(): ?string
    {
        return $this->kinescopeIngestUrl;
    }

    public function setKinescopeIngestUrl(?string $kinescopeIngestUrl): static
    {
        $this->kinescopeIngestUrl = null !== $kinescopeIngestUrl && '' !== trim($kinescopeIngestUrl)
            ? trim($kinescopeIngestUrl)
            : null;

        return $this;
    }

    public function getKinescopeStreamKey(): ?string
    {
        return $this->kinescopeStreamKey;
    }

    public function setKinescopeStreamKey(?string $kinescopeStreamKey): static
    {
        $this->kinescopeStreamKey = null !== $kinescopeStreamKey && '' !== trim($kinescopeStreamKey)
            ? trim($kinescopeStreamKey)
            : null;

        return $this;
    }

    public function getKinescopeChatEmbedUrl(): ?string
    {
        return $this->kinescopeChatEmbedUrl;
    }

    public function setKinescopeChatEmbedUrl(?string $kinescopeChatEmbedUrl): static
    {
        $this->kinescopeChatEmbedUrl = null !== $kinescopeChatEmbedUrl && '' !== trim($kinescopeChatEmbedUrl)
            ? trim($kinescopeChatEmbedUrl)
            : null;

        return $this;
    }

    public function getPosterUrl(): ?string
    {
        return $this->posterUrl;
    }

    public function setPosterUrl(?string $posterUrl): static
    {
        $this->posterUrl = null !== $posterUrl && '' !== trim($posterUrl) ? trim($posterUrl) : null;

        return $this;
    }

    public function getDurationSeconds(): ?int
    {
        return $this->durationSeconds;
    }

    public function setDurationSeconds(?int $durationSeconds): static
    {
        $this->durationSeconds = $durationSeconds;

        return $this;
    }

    public function getRecordingReadyAt(): ?\DateTimeImmutable
    {
        return $this->recordingReadyAt;
    }

    public function setRecordingReadyAt(?\DateTimeImmutable $recordingReadyAt): static
    {
        $this->recordingReadyAt = $recordingReadyAt;

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

    public function getDebitedAt(): ?\DateTimeImmutable
    {
        return $this->debitedAt;
    }

    public function setDebitedAt(?\DateTimeImmutable $debitedAt): static
    {
        $this->debitedAt = $debitedAt;

        return $this;
    }

    public function isDebited(): bool
    {
        return null !== $this->debitedAt;
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

    public function isSpeak(): bool
    {
        return PracticeSessionKind::Speak === $this->kind;
    }

    public function hasPlayerEmbed(): bool
    {
        if ($this->isSpeak()) {
            return null !== $this->kinescopeRoomCode || null !== $this->kinescopeVideoId;
        }

        return null !== $this->kinescopeVideoId || null !== $this->kinescopeEventId;
    }

    public function playerEmbedId(): ?string
    {
        return $this->kinescopeVideoId ?? $this->kinescopeEventId;
    }

    public function speakEmbedUrl(): ?string
    {
        if (null !== $this->kinescopeRoomCode) {
            return 'https://speak.kinescope.io/'.$this->kinescopeRoomCode;
        }

        return null;
    }

    /**
     * Relative room URL for EasyAdmin UrlField (avoids empty «Недоступно»).
     */
    public function getRoomLink(): string
    {
        return '/practice/room';
    }

    /** Non-empty placeholder so EasyAdmin renders the howto template. */
    public function getHowToBroadcast(): string
    {
        return 'howto';
    }

    public function hasKinescopeRemote(): bool
    {
        return null !== $this->kinescopeEventId || null !== $this->kinescopeRoomId;
    }

    public function chatEmbedUrl(): ?string
    {
        if (null !== $this->kinescopeChatEmbedUrl) {
            return $this->kinescopeChatEmbedUrl;
        }

        if (null !== $this->kinescopeEventId) {
            return 'https://kinescope.io/chat/'.$this->kinescopeEventId;
        }

        return null;
    }

    public function __toString(): string
    {
        return '' !== $this->title ? $this->title : 'Сессия #'.($this->id ?? '?');
    }
}
