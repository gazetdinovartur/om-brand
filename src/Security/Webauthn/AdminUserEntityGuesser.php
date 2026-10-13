<?php

declare(strict_types=1);

namespace App\Security\Webauthn;

use App\Entity\AdminUser;
use App\Repository\WebauthnUserEntityRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Webauthn\Bundle\Exception\MissingUserEntityException;
use Webauthn\Bundle\Security\Guesser\UserEntityGuesser;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Registers admin passkeys against AdminUser — never the linked SiteUser with the same email.
 */
final readonly class AdminUserEntityGuesser implements UserEntityGuesser
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private WebauthnUserEntityRepository $userEntities,
    ) {
    }

    public function findUserEntity(Request $request): PublicKeyCredentialUserEntity
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof AdminUser) {
            throw MissingUserEntityException::create('Admin passkey registration requires an AdminUser session.');
        }

        return $this->userEntities->createFromAdmin($user);
    }
}
