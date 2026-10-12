<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SiteUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/** @extends ServiceEntityRepository<SiteUser> */
final class SiteUserRepository extends ServiceEntityRepository implements UserProviderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteUser::class);
    }

    public function findOneByEmail(string $email): ?SiteUser
    {
        return $this->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    public function findOneByTelegramId(int|string $telegramId): ?SiteUser
    {
        return $this->findOneBy(['telegramId' => (string) $telegramId]);
    }

    public function findOneByPublicId(string $publicId): ?SiteUser
    {
        return $this->findOneBy(['publicId' => $publicId]);
    }

    public function findOneByWebauthnUserHandle(string $handle): ?SiteUser
    {
        return $this->findOneBy(['webauthnUserHandle' => $handle]);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->findOneByPublicId($identifier)
            ?? $this->findOneByEmail($identifier);

        if (!$user instanceof SiteUser) {
            throw new UserNotFoundException(sprintf('Site user "%s" not found.', $identifier));
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof SiteUser) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', $user::class));
        }

        $refreshed = $this->find($user->getId());
        if (!$refreshed instanceof SiteUser) {
            throw new UserNotFoundException('Site user no longer exists.');
        }

        return $refreshed;
    }

    public function supportsClass(string $class): bool
    {
        return SiteUser::class === $class || is_subclass_of($class, SiteUser::class);
    }
}
