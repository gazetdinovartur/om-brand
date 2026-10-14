<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\AdminUser;
use App\Entity\SiteUser;
use App\Service\SiteUserResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SiteUserResolverTest extends KernelTestCase
{
    public function testFindOrCreateByEmailIsIdempotent(): void
    {
        self::bootKernel();
        $resolver = static::getContainer()->get(SiteUserResolver::class);
        $email = 'resolver-'.bin2hex(random_bytes(3)).'@example.test';

        $first = $resolver->findOrCreateByEmail($email, 'Первый');
        $second = $resolver->findOrCreateByEmail($email, 'Второй');

        self::assertSame($first->getId(), $second->getId());
        self::assertSame('Первый', $first->getDisplayName());
    }

    public function testEnsureForAdminReusesExistingSiteUserWithSameEmail(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $resolver = static::getContainer()->get(SiteUserResolver::class);

        $email = 'admin-link-'.bin2hex(random_bytes(3)).'@example.test';
        $existing = (new SiteUser())->setEmail($email)->setDisplayName('Already');
        $admin = (new AdminUser())->setEmail($email)->setPassword('unused');
        $em->persist($existing);
        $em->persist($admin);
        $em->flush();

        $linked = $resolver->ensureForAdmin($admin);

        self::assertSame($existing->getId(), $linked->getId());
    }

    public function testResolveReturnsSiteUserAsIs(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $resolver = static::getContainer()->get(SiteUserResolver::class);

        $user = (new SiteUser())->setEmail('direct-'.bin2hex(random_bytes(3)).'@example.test')->setDisplayName('Direct');
        $em->persist($user);
        $em->flush();

        self::assertSame($user->getId(), $resolver->resolve($user)?->getId());
    }

    public function testFindOrCreateByTelegram(): void
    {
        self::bootKernel();
        $resolver = static::getContainer()->get(SiteUserResolver::class);
        $telegramId = (string) random_int(1000000, 9999999);

        $first = $resolver->findOrCreateByTelegram([
            'id' => $telegramId,
            'username' => 'tg_user_'.$telegramId,
            'first_name' => 'Телеграм',
        ]);
        $second = $resolver->findOrCreateByTelegram([
            'id' => $telegramId,
            'username' => 'tg_user_'.$telegramId,
        ]);

        self::assertSame($first->getId(), $second->getId());
        self::assertSame($telegramId, $first->getTelegramId());
        self::assertSame('tg_user_'.$telegramId, $first->getTelegramUsername());
    }
}
