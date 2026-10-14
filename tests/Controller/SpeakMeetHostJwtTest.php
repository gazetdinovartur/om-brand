<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AdminUser;
use App\Entity\SpeakMeeting;
use App\Infrastructure\Kinescope\KinescopeSpeakJwt;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Host JWT на /meet: без container->set после первого Twig-рендера (ломает globals).
 * Админ через loginUser(app); JWT подставляем до любого HTTP.
 */
final class SpeakMeetHostJwtTest extends WebTestCase
{
    public function testAdminMeetEmbedIncludesHostTokenWhenJwtConfigured(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = static::getContainer();

        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => \OPENSSL_KEYTYPE_RSA,
        ]);
        self::assertNotFalse($key);
        $privatePem = '';
        self::assertTrue(openssl_pkey_export($key, $privatePem));
        $container->set(KinescopeSpeakJwt::class, new KinescopeSpeakJwt($privatePem, 'test-host-kid'));

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $meeting = (new SpeakMeeting())
            ->setTitle('Host JWT meet')
            ->setKinescopeRoomId('room-jwt-1')
            ->setKinescopeRoomCode('jwt-room-code')
            ->setSpeakEmbedUrl('https://speak.kinescope.io/jwt-room-code');
        $em->persist($meeting);
        $em->flush();

        $admin = $em->getRepository(AdminUser::class)->findOneBy(['email' => 'speak-jwt-admin@localhost']);
        if (!$admin instanceof AdminUser) {
            $admin = (new AdminUser())->setEmail('speak-jwt-admin@localhost')->setPassword('unused');
            $em->persist($admin);
            $em->flush();
        }

        $client->loginUser($admin, 'app');

        $crawler = $client->request('GET', '/meet/'.$meeting->getToken());
        self::assertResponseIsSuccessful();
        $src = (string) $crawler->filter('iframe')->attr('src');
        self::assertStringStartsWith('https://speak.kinescope.io/jwt-room-code?token=', $src);
    }

    public function testGuestMeetEmbedHasNoToken(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $meeting = (new SpeakMeeting())
            ->setTitle('Guest meet')
            ->setKinescopeRoomId('room-guest-1')
            ->setKinescopeRoomCode('guest-code')
            ->setSpeakEmbedUrl('https://speak.kinescope.io/guest-code');
        $em->persist($meeting);
        $em->flush();

        $client->request('GET', '/meet/'.$meeting->getToken());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('iframe[src="https://speak.kinescope.io/guest-code"]');
    }
}
