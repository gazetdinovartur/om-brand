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
        $siteUser = $this->siteUsers->findOneByPublicId($username)
            ?? $this->siteUsers->findOneByEmail($username);
        if ($siteUser instanceof SiteUser) {
            return $this->fromSiteUser($siteUser);
        }

        return $this->fromAdmin($this->admins->findOneBy(['email' => $username]));
    }

    public function findOneByUserHandle(string $userHandle): ?PublicKeyCredentialUserEntity
    {
        $siteUser = $this->siteUsers->findOneByWebauthnUserHandle($userHandle);
        if ($siteUser instanceof SiteUser) {
            return $this->fromSiteUser($siteUser);
        }

        return $this->fromAdmin($this->admins->findOneBy(['webauthnUserHandle' => $userHandle]));
    }

    private function fromSiteUser(SiteUser $user): PublicKeyCredentialUserEntity
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

    private function fromAdmin(?AdminUser $admin): ?PublicKeyCredentialUserEntity
    {
        if (!$admin instanceof AdminUser) {
            return null;
        }

        return new PublicKeyCredentialUserEntity(
            $admin->getUserIdentifier(),
            $admin->getWebauthnUserHandle(),
            $admin->getEmail(),
        );
    }
}
