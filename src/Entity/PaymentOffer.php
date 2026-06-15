<?php

namespace App\Entity;

use App\Enum\PaymentOfferStatus;
use App\Repository\PaymentOfferRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PaymentOfferRepository::class)]
#[ORM\Table(name: 'payment_offer')]
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

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column]
    private int $amount = 0;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $sberPaymentUrl = null;

    #[ORM\Column(enumType: PaymentOfferStatus::class)]
    private PaymentOfferStatus $status = PaymentOfferStatus::Pending;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

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

    public function getSberPaymentUrl(): ?string
    {
        return $this->sberPaymentUrl;
    }

    public function setSberPaymentUrl(?string $sberPaymentUrl): static
    {
        $this->sberPaymentUrl = $sberPaymentUrl;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
