<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AdminUser;
use App\Entity\SiteUser;
use Webauthn\Bundle\Repository\PublicKeyCredentialUserEntityRepositoryInterface;
use Webauthn\PublicKeyCredentialUserEntity;

final readonly class WebauthnUserEntityRepository implements PublicKeyCredentialUserEntityRepositoryInterface
{
    public function __construct(
        private AdminUserRepository $admins,
        private SiteUserRepository $siteUsers,
    ) {
    }

    public function findOneByUsername(string $username): ?PublicKeyCredentialUserEntity
    {
        // SiteUser::getUserIdentifier() is publicId (UUID). Prefer that so an admin email
        // never resolves to the linked SiteUser created by SiteUserResolver.
        $siteUser = $this->siteUsers->findOneByPublicId($username);
        if ($siteUser instanceof SiteUser) {
            return $this->createFromSiteUser($siteUser);
        }

        $admin = $this->admins->findOneBy(['email' => $username]);
        if ($admin instanceof AdminUser) {
            return $this->createFromAdmin($admin);
        }

        // Email login options / legacy lookups: only when no AdminUser owns that email.
        $siteUser = $this->siteUsers->findOneByEmail($username);
        if ($siteUser instanceof SiteUser) {
            return $this->createFromSiteUser($siteUser);
        }

        return null;
    }

    public function findOneByUserHandle(string $userHandle): ?PublicKeyCredentialUserEntity
    {
        $siteUser = $this->siteUsers->findOneByWebauthnUserHandle($userHandle);
        if ($siteUser instanceof SiteUser) {
            return $this->createFromSiteUser($siteUser);
        }

        $admin = $this->admins->findOneBy(['webauthnUserHandle' => $userHandle]);
        if ($admin instanceof AdminUser) {
            return $this->createFromAdmin($admin);
        }

        return null;
    }

    public function createFromSiteUser(SiteUser $user): PublicKeyCredentialUserEntity
    {
        $display = $user->getDisplayName();
        if ('' === $display) {
            $display = $user->getEmail() ?? $user->getUserIdentifier();
        }

        return new PublicKeyCredentialUserEntity(
            $user->getUserIdentifier(),
            $user->getWebauthnUserHandle(),
            $display,
        );
    }

    public function createFromAdmin(AdminUser $admin): PublicKeyCredentialUserEntity
    {
        return new PublicKeyCredentialUserEntity(
            $admin->getUserIdentifier(),
            $admin->getWebauthnUserHandle(),
            $admin->getEmail(),
        );
    }
}
