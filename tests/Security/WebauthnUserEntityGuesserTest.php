<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\AdminUser;
use App\Entity\SiteUser;
use App\Security\Webauthn\AdminUserEntityGuesser;
use App\Security\Webauthn\SiteUserEntityGuesser;
use App\Service\SiteUserResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Webauthn\Bundle\Exception\MissingUserEntityException;

final class WebauthnUserEntityGuesserTest extends KernelTestCase
{
    public function testAdminGuesserUsesAdminHandleEvenWhenSiteUserSharesEmail(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        $email = 'passkey-admin-guess-'.bin2hex(random_bytes(4)).'@example.com';
        $admin = (new AdminUser())->setEmail($email)->setPassword('unused');
        $siteUser = (new SiteUser())->setEmail($email)->setDisplayName('Owner');
        $em->persist($admin);
        $em->persist($siteUser);
        $em->flush();

        $tokenStorage = $container->get(TokenStorageInterface::class);
        $tokenStorage->setToken(new UsernamePasswordToken($admin, 'admin', $admin->getRoles()));

        $entity = $container->get(AdminUserEntityGuesser::class)
            ->findUserEntity(Request::create('/admin/passkeys/register/options'));

        self::assertSame($email, $entity->name);
        self::assertSame($admin->getWebauthnUserHandle(), $entity->id);
        self::assertNotSame($siteUser->getWebauthnUserHandle(), $entity->id);
    }

    public function testSiteGuesserResolvesLinkedSiteUserFromAdminSession(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        $email = 'passkey-site-guess-'.bin2hex(random_bytes(4)).'@example.com';
        $admin = (new AdminUser())->setEmail($email)->setPassword('unused');
        $em->persist($admin);
        $em->flush();

        $tokenStorage = $container->get(TokenStorageInterface::class);
        $tokenStorage->setToken(new UsernamePasswordToken($admin, 'main', $admin->getRoles()));

        $siteUser = $container->get(SiteUserResolver::class)->ensureForAdmin($admin);
        $entity = $container->get(SiteUserEntityGuesser::class)
            ->findUserEntity(Request::create('/library/passkeys/register/options'));

        self::assertSame($siteUser->getPublicId(), $entity->name);
        self::assertSame($siteUser->getWebauthnUserHandle(), $entity->id);
    }

    public function testAdminGuesserRejectsSiteUserSession(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        $siteUser = (new SiteUser())
            ->setEmail('passkey-reject-'.bin2hex(random_bytes(4)).'@example.com')
            ->setDisplayName('Reader');
        $em->persist($siteUser);
        $em->flush();

        $tokenStorage = $container->get(TokenStorageInterface::class);
        $tokenStorage->setToken(new UsernamePasswordToken($siteUser, 'main', $siteUser->getRoles()));

        $this->expectException(MissingUserEntityException::class);
        $container->get(AdminUserEntityGuesser::class)
            ->findUserEntity(Request::create('/admin/passkeys/register/options'));
    }
}
