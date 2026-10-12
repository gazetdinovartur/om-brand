<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AdminUser;
use App\Entity\SiteUser;
use App\Repository\SiteUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Возвращает SiteUser для текущей сессии: сам SiteUser или связанный с AdminUser по email.
 */
final class SiteUserResolver
{
    public function __construct(
        private readonly SiteUserRepository $siteUsers,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function resolve(?UserInterface $user): ?SiteUser
    {
        if ($user instanceof SiteUser) {
            return $user;
        }

        if ($user instanceof AdminUser) {
            return $this->ensureForAdmin($user);
        }

        return null;
    }

    public function ensureForAdmin(AdminUser $admin): SiteUser
    {
        $email = mb_strtolower(trim($admin->getEmail()));
        $existing = $this->siteUsers->findOneByEmail($email);
        if ($existing instanceof SiteUser) {
            return $existing;
        }

        $siteUser = (new SiteUser())
            ->setEmail($email)
            ->setDisplayName($email);
        $this->entityManager->persist($siteUser);
        $this->entityManager->flush();

        return $siteUser;
    }

    public function findOrCreateByEmail(string $email, ?string $displayName = null): SiteUser
    {
        $email = mb_strtolower(trim($email));
        $existing = $this->siteUsers->findOneByEmail($email);
        if ($existing instanceof SiteUser) {
            return $existing;
        }

        $siteUser = (new SiteUser())
            ->setEmail($email)
            ->setDisplayName($displayName ?: $email);
        $this->entityManager->persist($siteUser);
        $this->entityManager->flush();

        return $siteUser;
    }

    /**
     * @param array{id: int|string, username?: ?string, first_name?: ?string, last_name?: ?string} $telegramUser
     */
    public function findOrCreateByTelegram(array $telegramUser): SiteUser
    {
        $telegramId = (string) $telegramUser['id'];
        $existing = $this->siteUsers->findOneByTelegramId($telegramId);
        if ($existing instanceof SiteUser) {
            if (!empty($telegramUser['username'])) {
                $existing->setTelegramUsername((string) $telegramUser['username']);
            }

            return $existing;
        }

        $display = trim(implode(' ', array_filter([
            $telegramUser['first_name'] ?? null,
            $telegramUser['last_name'] ?? null,
        ])));
        if ('' === $display && !empty($telegramUser['username'])) {
            $display = (string) $telegramUser['username'];
        }
        if ('' === $display) {
            $display = 'Читатель';
        }

        $siteUser = (new SiteUser())
            ->setTelegramId($telegramId)
            ->setTelegramUsername(isset($telegramUser['username']) ? (string) $telegramUser['username'] : null)
            ->setDisplayName($display);
        $this->entityManager->persist($siteUser);
        $this->entityManager->flush();

        return $siteUser;
    }
}
