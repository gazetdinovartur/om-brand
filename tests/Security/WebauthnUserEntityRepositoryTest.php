<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\AdminUser;
use App\Entity\SiteUser;
use App\Repository\WebauthnUserEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class WebauthnUserEntityRepositoryTest extends KernelTestCase
{
    public function testEmailLookupPrefersAdminOverLinkedSiteUser(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $repository = static::getContainer()->get(WebauthnUserEntityRepository::class);

        $email = 'passkey-owner-'.bin2hex(random_bytes(4)).'@example.com';
        $admin = (new AdminUser())->setEmail($email)->setPassword('unused');
        $siteUser = (new SiteUser())->setEmail($email)->setDisplayName('Owner');
        $em->persist($admin);
        $em->persist($siteUser);
        $em->flush();

        $entity = $repository->findOneByUsername($email);

        self::assertNotNull($entity);
        self::assertSame($email, $entity->name);
        self::assertSame($admin->getWebauthnUserHandle(), $entity->id);
        self::assertNotSame($siteUser->getWebauthnUserHandle(), $entity->id);
    }

    public function testPublicIdStillResolvesSiteUser(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $repository = static::getContainer()->get(WebauthnUserEntityRepository::class);

        $siteUser = (new SiteUser())
            ->setEmail('passkey-reader-'.bin2hex(random_bytes(4)).'@example.com')
            ->setDisplayName('Reader');
        $em->persist($siteUser);
        $em->flush();

        $entity = $repository->findOneByUsername($siteUser->getPublicId());

        self::assertNotNull($entity);
        self::assertSame($siteUser->getPublicId(), $entity->name);
        self::assertSame($siteUser->getWebauthnUserHandle(), $entity->id);
    }

    public function testUserHandlesStayIndependentForAdminAndSiteUser(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $repository = static::getContainer()->get(WebauthnUserEntityRepository::class);

        $email = 'passkey-handles-'.bin2hex(random_bytes(4)).'@example.com';
        $admin = (new AdminUser())->setEmail($email)->setPassword('unused');
        $siteUser = (new SiteUser())->setEmail($email)->setDisplayName('Owner');
        $em->persist($admin);
        $em->persist($siteUser);
        $em->flush();

        $fromAdmin = $repository->findOneByUserHandle($admin->getWebauthnUserHandle());
        $fromSite = $repository->findOneByUserHandle($siteUser->getWebauthnUserHandle());

        self::assertSame($email, $fromAdmin?->name);
        self::assertSame($siteUser->getPublicId(), $fromSite?->name);
        self::assertNotSame($fromAdmin?->id, $fromSite?->id);
    }
}
