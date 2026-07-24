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
    public function testSearchEndpointReturnsLiveResultsWithoutMisses(): void
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

        $client->request('GET', '/chronicle/search?q='.rawurlencode($token));
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        /** @var array{status: string, total: int, html: string, feedUrl: string} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame('results', $payload['status']);
        self::assertGreaterThanOrEqual(3, $payload['total']);
        self::assertStringContainsString((string) $hitTitle->getSlug(), $payload['html']);
        self::assertStringContainsString((string) $hitLede->getSlug(), $payload['html']);
        self::assertStringContainsString((string) $hitBody->getSlug(), $payload['html']);
        self::assertStringNotContainsString((string) $miss->getSlug(), $payload['html']);
        self::assertStringContainsString('q='.$token, $payload['feedUrl']);
    }

    public function testHubRendersSearchModalAndFeedFilter(): void
    {
        $client = static::createClient();
        $client->request('GET', '/chronicle');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('data-chronicle-search-modal', $html);
        self::assertStringContainsString('data-chronicle-search-results', $html);
        self::assertStringContainsString('/chronicle/search', $html);
    }

    public function testIdleSearchReturnsEmptyHtml(): void
    {
        $client = static::createClient();
        $client->request('GET', '/chronicle/search');
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('idle', $payload['status']);
        self::assertSame('', $payload['html']);
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
