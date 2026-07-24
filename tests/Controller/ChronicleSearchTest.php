<?php

namespace App\Tests\Controller;

use App\Entity\ChronicleBlock;
use App\Entity\ChronicleEntry;
use App\Enum\ChronicleBlockType;
use App\Enum\ChronicleStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ChronicleSearchTest extends WebTestCase
{
    public function testSearchMatchesTitleLedeAndBlockBody(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $token = 'srch'.bin2hex(random_bytes(4));

        $hitTitle = $this->publishedEntry('Title '.$token.' alpha', 'plain lede', 'body without token');
        $hitLede = $this->publishedEntry('Other title one', 'lede '.$token.' beta', 'body without token');
        $hitBody = $this->publishedEntry('Other title two', 'plain lede', 'paragraph '.$token.' gamma');
        $miss = $this->publishedEntry('Unrelated post', 'nothing here', 'still nothing');

        $em->persist($hitTitle);
        $em->persist($hitLede);
        $em->persist($hitBody);
        $em->persist($miss);
        $em->flush();

        $client->request('GET', '/chronicle?q='.rawurlencode($token));
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('data-chronicle-search', $html);
        self::assertStringContainsString('Поиск: <strong>«'.$token.'»</strong>', $html);
        self::assertStringContainsString((string) $hitTitle->getSlug(), $html);
        self::assertStringContainsString((string) $hitLede->getSlug(), $html);
        self::assertStringContainsString((string) $hitBody->getSlug(), $html);
        self::assertStringNotContainsString((string) $miss->getSlug(), $html);
    }

    private function publishedEntry(string $title, string $lede, string $body): ChronicleEntry
    {
        $entry = new ChronicleEntry();
        $entry->setTitle($title);
        $entry->setSlug('search-'.bin2hex(random_bytes(4)));
        $entry->setShortHash(substr(bin2hex(random_bytes(4)), 0, 8));
        $entry->setLede($lede);
        $entry->setStatus(ChronicleStatus::Published);
        $entry->setPublishedAt(new \DateTimeImmutable('-1 day'));
        $entry->setSortOrder(-500);

        $block = new ChronicleBlock();
        $block->setType(ChronicleBlockType::Paragraph);
        $block->setBody($body);
        $block->setSortOrder(0);
        $entry->addBlock($block);

        return $entry;
    }
}
