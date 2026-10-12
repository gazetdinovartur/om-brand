<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AdminUser;
use App\Entity\SiteUser;
use App\Entity\WebauthnCredential;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Webauthn\Bundle\Repository\CanSaveCredentialRecord;
use Webauthn\Bundle\Repository\CredentialRecordRepositoryInterface;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialUserEntity;

/** @extends ServiceEntityRepository<WebauthnCredential> */
final class WebauthnCredentialRepository extends ServiceEntityRepository implements
    CredentialRecordRepositoryInterface,
    CanSaveCredentialRecord
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebauthnCredential::class);
    }

    /** @return list<CredentialRecord> */
    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        return $this->findBy(
            ['userHandle' => $publicKeyCredentialUserEntity->id],
            ['createdAt' => 'ASC'],
        );
    }

    public function findOneByCredentialId(string $publicKeyCredentialId): ?CredentialRecord
    {
        return $this->findOneBy([
            'credentialIdHash' => hash('sha256', $publicKeyCredentialId),
        ]);
    }

    public function saveCredentialRecord(CredentialRecord $credentialRecord): void
    {
        if ($credentialRecord instanceof WebauthnCredential) {
            $credential = $credentialRecord;
        } else {
            $existing = $this->findOneByCredentialId($credentialRecord->publicKeyCredentialId);
            if ($existing instanceof WebauthnCredential) {
                $this->copyMutableRecordData($credentialRecord, $existing);
                $credential = $existing;
            } else {
                $credential = WebauthnCredential::fromCredentialRecord($credentialRecord);
            }
        }

        $credential->markUsed();
        $this->getEntityManager()->persist($credential);
        $this->getEntityManager()->flush();
    }

    /** @return list<WebauthnCredential> */
    public function findForAdmin(AdminUser $admin): array
    {
        return $this->findByHandle($admin->getWebauthnUserHandle());
    }

    /** @return list<WebauthnCredential> */
    public function findForSiteUser(SiteUser $user): array
    {
        return $this->findByHandle($user->getWebauthnUserHandle());
    }

    /** @return list<WebauthnCredential> */
    private function findByHandle(string $userHandle): array
    {
        return $this->findBy(
            ['userHandle' => $userHandle],
            ['createdAt' => 'ASC'],
        );
    }

    private function copyMutableRecordData(CredentialRecord $source, WebauthnCredential $target): void
    {
        $target->counter = $source->counter;
        $target->transports = $source->transports;
        $target->otherUI = $source->otherUI;
        $target->backupEligible = $source->backupEligible;
        $target->backupStatus = $source->backupStatus;
        $target->uvInitialized = $source->uvInitialized;
    }
}
