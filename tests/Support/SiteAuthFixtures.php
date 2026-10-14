<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\SiteLoginChallenge;
use App\Entity\SiteUser;
use App\Entity\WebauthnCredential;
use App\Repository\SiteUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Uid\Uuid;
use Webauthn\TrustPath\EmptyTrustPath;

trait SiteAuthFixtures
{
    /**
     * Реальный вход через email-код (Security::login) — loginUser на main здесь не держит сессию.
     */
    private function loginViaEmailCode(
        KernelBrowser $client,
        EntityManagerInterface $em,
        ?string $email = null,
        string $targetPath = '/account',
        string $code = '123456',
    ): SiteUser {
        $email ??= 'auth-'.bin2hex(random_bytes(4)).'@example.test';

        $em->persist(new SiteLoginChallenge(
            $email,
            hash('sha256', $code),
            new \DateTimeImmutable('+15 minutes'),
        ));
        $em->flush();

        $client->request('POST', '/library/login', [
            'action' => 'email_verify',
            'email' => $email,
            'code' => $code,
            '_target_path' => $targetPath,
        ], server: [
            'REMOTE_ADDR' => $this->uniqueClientIp(),
        ]);

        self::assertTrue($client->getResponse()->isRedirect(), 'email verify must redirect after login');

        /** @var SiteUserRepository $users */
        $users = $em->getRepository(SiteUser::class);
        $user = $users->findOneByEmail($email);
        self::assertInstanceOf(SiteUser::class, $user);

        return $user;
    }

    private function createPasskeyForUser(EntityManagerInterface $em, SiteUser $user, string $label = 'Тестовый ключ'): WebauthnCredential
    {
        $credential = new WebauthnCredential(
            publicKeyCredentialId: 'cred-'.bin2hex(random_bytes(16)),
            type: 'public-key',
            transports: ['internal'],
            attestationType: 'none',
            trustPath: EmptyTrustPath::create(),
            aaguid: Uuid::fromString('00000000-0000-0000-0000-000000000000'),
            credentialPublicKey: 'test-public-key-'.bin2hex(random_bytes(8)),
            userHandle: $user->getWebauthnUserHandle(),
            counter: 0,
        );
        $credential->setLabel($label);
        $em->persist($credential);
        $em->flush();

        return $credential;
    }

    private function uniqueClientIp(): string
    {
        return sprintf('10.%d.%d.%d', random_int(1, 254), random_int(1, 254), random_int(1, 254));
    }
}
