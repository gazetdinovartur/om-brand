<?php

namespace App\Entity;

use App\Enum\PaymentOfferStatus;
use App\Practice\PracticeFormat;
use App\Repository\PaymentOfferRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PaymentOfferRepository::class)]
#[ORM\Table(name: 'payment_offer')]
#[ORM\UniqueConstraint(name: 'uniq_payment_offer_yookassa', columns: ['yookassa_payment_id'])]
class PaymentOffer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $token;

    #[ORM\ManyToOne(inversedBy: 'paymentOffers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Inquiry $inquiry = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?SiteUser $siteUser = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column]
    private int $amount = 0;

    /** Confirmation / external pay URL (YooKassa confirmation_url or legacy link). */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $paymentUrl = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $yookassaPaymentId = null;

    #[ORM\Column(enumType: PaymentOfferStatus::class)]
    private PaymentOfferStatus $status = PaymentOfferStatus::Pending;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    /** Practice format value: first|single|cycle. */
    #[ORM\Column(length: 16, nullable: true)]
    private ?string $practiceFormat = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $sessionsTotal = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $sessionsLeft = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->token = Uuid::v7()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = new \DateTimeImmutable('+30 days');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getInquiry(): ?Inquiry
    {
        return $this->inquiry;
    }

    public function setInquiry(?Inquiry $inquiry): static
    {
        $this->inquiry = $inquiry;

        return $this;
    }

    public function getSiteUser(): ?SiteUser
    {
        return $this->siteUser;
    }

    public function setSiteUser(?SiteUser $siteUser): static
    {
        $this->siteUser = $siteUser;

        return $this;
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

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getAmountRubles(): float
    {
        return $this->amount / 100;
    }

    public function getPaymentUrl(): ?string
    {
        return $this->paymentUrl;
    }

    public function setPaymentUrl(?string $paymentUrl): static
    {
        $this->paymentUrl = $paymentUrl;

        return $this;
    }

    /** @deprecated use getPaymentUrl() */
    public function getSberPaymentUrl(): ?string
    {
        return $this->paymentUrl;
    }

    /** @deprecated use setPaymentUrl() */
    public function setSberPaymentUrl(?string $sberPaymentUrl): static
    {
        return $this->setPaymentUrl($sberPaymentUrl);
    }

    public function getYookassaPaymentId(): ?string
    {
        return $this->yookassaPaymentId;
    }

    public function setYookassaPaymentId(?string $yookassaPaymentId): static
    {
        $this->yookassaPaymentId = $yookassaPaymentId;

        return $this;
    }

    public function getStatus(): PaymentOfferStatus
    {
        return $this->status;
    }

    public function setStatus(PaymentOfferStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function isExpired(): bool
    {
        return null !== $this->expiresAt && $this->expiresAt < new \DateTimeImmutable();
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getPracticeFormat(): ?string
    {
        return $this->practiceFormat;
    }

    public function setPracticeFormat(?string $practiceFormat): static
    {
        $this->practiceFormat = null !== $practiceFormat && '' !== trim($practiceFormat)
            ? trim($practiceFormat)
            : null;

        return $this;
    }

    public function getPracticeFormatEnum(): ?PracticeFormat
    {
        if (null === $this->practiceFormat) {
            return null;
        }

        return PracticeFormat::tryFrom($this->practiceFormat);
    }

    public function applyPracticeFormat(PracticeFormat $format): static
    {
        $count = $format->sessionsCount();
        $this->practiceFormat = $format->value;
        $this->sessionsTotal = $count;
        $this->sessionsLeft = $count;

        return $this;
    }

    public function isPracticeOffer(): bool
    {
        if (null !== $this->practiceFormat) {
            return true;
        }

        return null !== $this->note && str_contains($this->note, 'Практика /practice');
    }

    public function getSessionsTotal(): int
    {
        return $this->sessionsTotal;
    }

    public function setSessionsTotal(int $sessionsTotal): static
    {
        $this->sessionsTotal = max(0, $sessionsTotal);

        return $this;
    }

    public function getSessionsLeft(): int
    {
        return $this->sessionsLeft;
    }

    public function setSessionsLeft(int $sessionsLeft): static
    {
        $this->sessionsLeft = max(0, $sessionsLeft);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
