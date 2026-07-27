<?php

namespace App\Entity;

use App\Enum\EmailSubscriberStatus;
use App\Repository\EmailSubscriberRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmailSubscriberRepository::class)]
#[ORM\Table(name: 'email_subscriber')]
#[ORM\UniqueConstraint(name: 'uniq_email_subscriber_email', columns: ['email'])]
#[ORM\Index(name: 'idx_email_subscriber_confirm', columns: ['confirm_token'])]
#[ORM\Index(name: 'idx_email_subscriber_unsubscribe', columns: ['unsubscribe_token'])]
class EmailSubscriber
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 16, enumType: EmailSubscriberStatus::class)]
    private EmailSubscriberStatus $status;

    #[ORM\Column(length: 64)]
    private string $confirmToken;

    #[ORM\Column(length: 64)]
    private string $unsubscribeToken;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $email)
    {
        $this->email = mb_strtolower(trim($email));
        $this->status = EmailSubscriberStatus::Pending;
        $this->confirmToken = bin2hex(random_bytes(32));
        $this->unsubscribeToken = bin2hex(random_bytes(32));
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getStatus(): EmailSubscriberStatus
    {
        return $this->status;
    }

    public function getConfirmToken(): string
    {
        return $this->confirmToken;
    }

    public function getUnsubscribeToken(): string
    {
        return $this->unsubscribeToken;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function markPendingForConfirm(): void
    {
        $this->status = EmailSubscriberStatus::Pending;
        $this->confirmToken = bin2hex(random_bytes(32));
        $this->confirmedAt = null;
    }

    public function confirm(): void
    {
        $this->status = EmailSubscriberStatus::Confirmed;
        $this->confirmedAt = new \DateTimeImmutable();
        $this->confirmToken = bin2hex(random_bytes(32));
    }

    public function unsubscribe(): void
    {
        $this->status = EmailSubscriberStatus::Unsubscribed;
        $this->unsubscribeToken = bin2hex(random_bytes(32));
    }

    public function isConfirmed(): bool
    {
        return EmailSubscriberStatus::Confirmed === $this->status;
    }
}
