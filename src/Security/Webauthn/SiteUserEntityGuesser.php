<?php

declare(strict_types=1);

namespace App\Security\Webauthn;

use App\Entity\SiteUser;
use App\Repository\WebauthnUserEntityRepository;
use App\Service\SiteUserResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Webauthn\Bundle\Exception\MissingUserEntityException;
use Webauthn\Bundle\Security\Guesser\UserEntityGuesser;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Registers library passkeys against SiteUser, including when the session is AdminUser.
 */
final readonly class SiteUserEntityGuesser implements UserEntityGuesser
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private SiteUserResolver $siteUsers,
        private WebauthnUserEntityRepository $userEntities,
    ) {
    }

    public function findUserEntity(Request $request): PublicKeyCredentialUserEntity
    {
        $siteUser = $this->siteUsers->resolve($this->tokenStorage->getToken()?->getUser());
        if (!$siteUser instanceof SiteUser) {
            throw MissingUserEntityException::create('Library passkey registration requires a SiteUser.');
        }

        return $this->userEntities->createFromSiteUser($siteUser);
    }
}
