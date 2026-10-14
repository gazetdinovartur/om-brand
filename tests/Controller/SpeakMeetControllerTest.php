<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\SpeakMeeting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SpeakMeetControllerTest extends WebTestCase
{
    public function testMeetPageRendersSpeakIframe(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $meeting = (new SpeakMeeting())
            ->setTitle('Созвон')
            ->setKinescopeRoomId('room-1')
            ->setKinescopeRoomCode('abc-defg-hij')
            ->setSpeakEmbedUrl('https://speak.kinescope.io/abc-defg-hij');
        $em->persist($meeting);
        $em->flush();

        $client->request('GET', '/meet/'.$meeting->getToken());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#meet-title', 'Созвон');
        self::assertSelectorExists('iframe[src="https://speak.kinescope.io/abc-defg-hij"]');
    }

    public function testUnknownMeetTokenIs404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/meet/00000000-0000-0000-0000-000000000099');
        self::assertResponseStatusCodeSame(404);
    }
}
