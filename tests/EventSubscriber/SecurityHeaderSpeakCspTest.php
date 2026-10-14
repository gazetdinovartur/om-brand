<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\Entity\SpeakMeeting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityHeaderSpeakCspTest extends WebTestCase
{
    public function testMeetResponseAllowsSpeakFrameSrc(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $meeting = (new SpeakMeeting())
            ->setTitle('CSP meet')
            ->setKinescopeRoomId('room-csp')
            ->setKinescopeRoomCode('csp-code')
            ->setSpeakEmbedUrl('https://speak.kinescope.io/csp-code');
        $em->persist($meeting);
        $em->flush();

        $client->request('GET', '/meet/'.$meeting->getToken());
        self::assertResponseIsSuccessful();

        $csp = (string) $client->getResponse()->headers->get('Content-Security-Policy');
        self::assertStringContainsString('https://speak.kinescope.io', $csp);
        self::assertStringContainsString('https://kinescope.io', $csp);
    }
}
