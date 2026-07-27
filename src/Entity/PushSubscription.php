<?php

namespace App\Entity;

use App\Repository\PushSubscriptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PushSubscriptionRepository::class)]
#[ORM\Table(name: 'push_subscription')]
#[ORM\UniqueConstraint(name: 'uniq_push_subscription_endpoint_hash', columns: ['endpoint_hash'])]
class PushSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $endpoint;

    #[ORM\Column(length: 64)]
    private string $endpointHash;

    #[ORM\Column(length: 255)]
    private string $p256dh;

    #[ORM\Column(length: 255)]
    private string $auth;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $visitorToken = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastSeenAt;

    public function __construct(string $endpoint, string $p256dh, string $auth, ?string $visitorToken = null)
    {
        $this->endpoint = $endpoint;
        $this->endpointHash = hash('sha256', $endpoint);
        $this->p256dh = $p256dh;
        $this->auth = $auth;
        $this->visitorToken = $visitorToken;
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->lastSeenAt = $now;
    }

    public static function hashEndpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getEndpointHash(): string
    {
        return $this->endpointHash;
    }

    public function getP256dh(): string
    {
        return $this->p256dh;
    }

    public function getAuth(): string
    {
        return $this->auth;
    }

    public function getVisitorToken(): ?string
    {
        return $this->visitorToken;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function refreshKeys(string $p256dh, string $auth, ?string $visitorToken = null): void
    {
        $this->p256dh = $p256dh;
        $this->auth = $auth;
        if (null !== $visitorToken) {
            $this->visitorToken = $visitorToken;
        }
        $this->lastSeenAt = new \DateTimeImmutable();
    }

    public function touch(): void
    {
        $this->lastSeenAt = new \DateTimeImmutable();
    }
}
