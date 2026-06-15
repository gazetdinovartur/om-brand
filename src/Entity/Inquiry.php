<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\ContactType;
use App\Enum\InquiryStatus;
use App\Enum\InquiryType;
use App\Repository\InquiryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: InquiryRepository::class)]
#[ORM\Table(name: 'inquiry')]
#[ORM\HasLifecycleCallbacks]
class Inquiry
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $uuid;

    #[ORM\Column(length: 120)]
    private string $name = '';

    #[ORM\Column(length: 255)]
    private string $contact = '';

    #[ORM\Column(enumType: ContactType::class)]
    private ContactType $contactType = ContactType::Telegram;

    #[ORM\Column(enumType: InquiryType::class)]
    private InquiryType $inquiryType = InquiryType::Unsure;

    #[ORM\Column(type: 'text')]
    private string $message = '';

    #[ORM\Column(enumType: InquiryStatus::class)]
    private InquiryStatus $status = InquiryStatus::New;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $attachmentPath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $attachmentOriginalName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $attachmentMimeType = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $adminNote = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $privacyConsentAt = null;

    /** @var Collection<int, PaymentOffer> */
    #[ORM\OneToMany(targetEntity: PaymentOffer::class, mappedBy: 'inquiry', orphanRemoval: true)]
    private Collection $paymentOffers;

    public function __construct()
    {
        $this->uuid = Uuid::v7();
        $this->paymentOffers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getContact(): string
    {
        return $this->contact;
    }

    public function setContact(string $contact): static
    {
        $this->contact = $contact;

        return $this;
    }

    public function getContactType(): ContactType
    {
        return $this->contactType;
    }

    public function setContactType(ContactType $contactType): static
    {
        $this->contactType = $contactType;

        return $this;
    }

    public function getInquiryType(): InquiryType
    {
        return $this->inquiryType;
    }

    public function setInquiryType(InquiryType $inquiryType): static
    {
        $this->inquiryType = $inquiryType;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getStatus(): InquiryStatus
    {
        return $this->status;
    }

    public function setStatus(InquiryStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getAttachmentPath(): ?string
    {
        return $this->attachmentPath;
    }

    public function setAttachmentPath(?string $attachmentPath): static
    {
        $this->attachmentPath = $attachmentPath;

        return $this;
    }

    public function getAttachmentOriginalName(): ?string
    {
        return $this->attachmentOriginalName;
    }

    public function setAttachmentOriginalName(?string $attachmentOriginalName): static
    {
        $this->attachmentOriginalName = $attachmentOriginalName;

        return $this;
    }

    public function getAttachmentMimeType(): ?string
    {
        return $this->attachmentMimeType;
    }

    public function setAttachmentMimeType(?string $attachmentMimeType): static
    {
        $this->attachmentMimeType = $attachmentMimeType;

        return $this;
    }

    public function hasAttachment(): bool
    {
        return null !== $this->attachmentPath;
    }

    public function getAdminNote(): ?string
    {
        return $this->adminNote;
    }

    public function setAdminNote(?string $adminNote): static
    {
        $this->adminNote = $adminNote;

        return $this;
    }

    public function getPrivacyConsentAt(): ?\DateTimeImmutable
    {
        return $this->privacyConsentAt;
    }

    public function setPrivacyConsentAt(?\DateTimeImmutable $privacyConsentAt): static
    {
        $this->privacyConsentAt = $privacyConsentAt;

        return $this;
    }

    /** @return Collection<int, PaymentOffer> */
    public function getPaymentOffers(): Collection
    {
        return $this->paymentOffers;
    }

    public function __toString(): string
    {
        return sprintf('#%s %s', $this->id ?? 'new', $this->name);
    }
}
