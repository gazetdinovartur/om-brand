<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WebauthnCredentialRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\TrustPath;

#[ORM\Entity(repositoryClass: WebauthnCredentialRepository::class)]
#[ORM\Table(name: 'webauthn_credential')]
class WebauthnCredential extends CredentialRecord
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $id;

    #[ORM\Column(length: 120)]
    private string $label = 'Ключ доступа';

    #[ORM\Column(length: 64, unique: true)]
    private string $credentialIdHash;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    /**
     * @param list<string>              $transports
     * @param array<string, mixed>|null $otherUI
     */
    public function __construct(
        string $publicKeyCredentialId,
        string $type,
        array $transports,
        string $attestationType,
        TrustPath $trustPath,
        Uuid $aaguid,
        string $credentialPublicKey,
        string $userHandle,
        int $counter,
        ?array $otherUI = null,
        ?bool $backupEligible = null,
        ?bool $backupStatus = null,
        ?bool $uvInitialized = null,
    ) {
        $this->id = Uuid::v7()->toRfc4122();
        $this->credentialIdHash = hash('sha256', $publicKeyCredentialId);
        $this->createdAt = new \DateTimeImmutable();
        parent::__construct(
            $publicKeyCredentialId,
            $type,
            $transports,
            $attestationType,
            $trustPath,
            $aaguid,
            $credentialPublicKey,
            $userHandle,
            $counter,
            $otherUI,
            $backupEligible,
            $backupStatus,
            $uvInitialized,
        );
    }

    public static function fromCredentialRecord(CredentialRecord $credential): self
    {
        return new self(
            $credential->publicKeyCredentialId,
            $credential->type,
            $credential->transports,
            $credential->attestationType,
            $credential->trustPath,
            $credential->aaguid,
            $credential->credentialPublicKey,
            $credential->userHandle,
            $credential->counter,
            $credential->otherUI,
            $credential->backupEligible,
            $credential->backupStatus,
            $credential->uvInitialized,
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getCredentialIdHash(): string
    {
        return $this->credentialIdHash;
    }

    public function setLabel(string $label): static
    {
        $label = trim($label);
        $this->label = '' === $label ? 'Ключ доступа' : $label;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function markUsed(?\DateTimeImmutable $at = null): void
    {
        $this->lastUsedAt = $at ?? new \DateTimeImmutable();
    }
}
