<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SiteUserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Универсальный пользователь сайта: библиотека и личное место.
 * Админка остаётся на AdminUser.
 */
#[ORM\Entity(repositoryClass: SiteUserRepository::class)]
#[ORM\Table(name: 'site_user')]
#[ORM\HasLifecycleCallbacks]
class SiteUser implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Стабильный идентификатор для сессии и WebAuthn user.name. */
    #[ORM\Column(length: 36, unique: true)]
    private string $publicId;

    #[ORM\Column(length: 180, unique: true, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'bigint', unique: true, nullable: true)]
    private ?string $telegramId = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $telegramUsername = null;

    #[ORM\Column(length: 120)]
    private string $displayName = '';

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(length: 36, unique: true)]
    private string $webauthnUserHandle;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->publicId = Uuid::v7()->toRfc4122();
        $this->webauthnUserHandle = Uuid::v7()->toRfc4122();
        $this->createdAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicId(): string
    {
        return $this->publicId;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $email = null === $email ? null : mb_strtolower(trim($email));
        $this->email = '' === $email ? null : $email;

        return $this;
    }

    public function getTelegramId(): ?string
    {
        return $this->telegramId;
    }

    public function setTelegramId(int|string|null $telegramId): static
    {
        if (null === $telegramId || '' === $telegramId) {
            $this->telegramId = null;

            return $this;
        }
        $this->telegramId = (string) $telegramId;

        return $this;
    }

    public function getTelegramUsername(): ?string
    {
        return $this->telegramUsername;
    }

    public function setTelegramUsername(?string $telegramUsername): static
    {
        $telegramUsername = null === $telegramUsername ? null : ltrim(trim($telegramUsername), '@');
        $this->telegramUsername = '' === $telegramUsername ? null : $telegramUsername;

        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): static
    {
        $this->displayName = trim($displayName);

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->publicId;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getWebauthnUserHandle(): string
    {
        return $this->webauthnUserHandle;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function markLoggedIn(?\DateTimeImmutable $at = null): static
    {
        $this->lastLoginAt = $at ?? new \DateTimeImmutable();

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function __toString(): string
    {
        if ('' !== $this->displayName) {
            return $this->displayName;
        }

        return $this->email ?? ('tg:'.($this->telegramId ?? $this->publicId));
    }
}
