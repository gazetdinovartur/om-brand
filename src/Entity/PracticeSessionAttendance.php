<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PracticeSessionAttendanceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PracticeSessionAttendanceRepository::class)]
#[ORM\Table(name: 'practice_session_attendance')]
#[ORM\UniqueConstraint(name: 'uniq_practice_attendance_session_offer', columns: ['session_id', 'payment_offer_id'])]
#[ORM\Index(columns: ['session_id'], name: 'idx_practice_attendance_session')]
class PracticeSessionAttendance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PracticeSession $session = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PaymentOffer $paymentOffer = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSession(): ?PracticeSession
    {
        return $this->session;
    }

    public function setSession(?PracticeSession $session): static
    {
        $this->session = $session;

        return $this;
    }

    public function getPaymentOffer(): ?PaymentOffer
    {
        return $this->paymentOffer;
    }

    public function setPaymentOffer(?PaymentOffer $paymentOffer): static
    {
        $this->paymentOffer = $paymentOffer;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
