<?php

namespace App\Tests\Admin;

use App\Entity\AdminUser;
use App\Entity\ChronicleEntry;
use App\Enum\ChronicleStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ChronicleAdminIndexTest extends WebTestCase
{
    private function loginAsAdmin(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $admin = $em->getRepository(AdminUser::class)->findOneBy(['email' => 'admin-test@localhost']);
        if (!$admin instanceof AdminUser) {
            $admin = new AdminUser();
            $admin->setEmail('admin-test@localhost');
            $em->persist($admin);
        }
        $admin->setPassword($hasher->hashPassword($admin, 'TestAdmin1!'));
        $em->flush();

        $crawler = $client->request('GET', '/admin/login');
        $form = $crawler->selectButton('Войти')->form([
            '_username' => 'admin-test@localhost',
            '_password' => 'TestAdmin1!',
        ]);
        $client->submit($form);
        if ($client->getResponse()->isRedirection()) {
            $client->followRedirect();
        }

        return $client;
    }

    public function testIndexShowsCustomFiltersAndDragHandles(): void
    {
        $client = $this->loginAsAdmin();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $entry = new ChronicleEntry();
        $entry->setTitle('Admin filter test '.uniqid('', true));
        $entry->setSlug('admin-filter-test-'.bin2hex(random_bytes(4)));
        $entry->setShortHash(substr(bin2hex(random_bytes(4)), 0, 8));
        $entry->setStatus(ChronicleStatus::Draft);
        $entry->setSortOrder(-100);
        $em->persist($entry);
        $em->flush();

        $client->request('GET', '/admin/chronicle-entry');
        while ($client->getResponse()->isRedirection()) {
            $loc = (string) $client->getResponse()->headers->get('Location');
            if (str_contains($loc, '/admin/login')) {
                self::fail('Still redirected to login after form auth; location='.$loc);
            }
            $client->followRedirect();
        }

        $content = (string) $client->getResponse()->getContent();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('chronicle-admin-filters', $content);
        self::assertStringContainsString('data-chronicle-id', $content);
        self::assertStringContainsString('admin-chronicle-index.js', $content);
        self::assertStringContainsString('♥ Избранное', $content);
        self::assertStringNotContainsString('action-filters-button', $content);

        $client->request('GET', '/admin/chronicle-entry?cf%5Bstatus%5D=draft');
        while ($client->getResponse()->isRedirection()) {
            $client->followRedirect();
        }
        $draftHtml = (string) $client->getResponse()->getContent();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('chronicle-admin-filters', $draftHtml);
        self::assertMatchesRegularExpression('/chronicle-admin-chip[^"]*is-active[^>]*>\\s*Черновик/u', $draftHtml);
    }

    public function testReorderEndpointAcceptsFilteredSubset(): void
    {
        $client = $this->loginAsAdmin();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $made = [];
        for ($i = 0; $i < 4; ++$i) {
            $entry = new ChronicleEntry();
            $entry->setTitle('Reorder '.$i.' '.uniqid('', true));
            $entry->setSlug('reorder-'.$i.'-'.bin2hex(random_bytes(3)));
            $entry->setShortHash(substr(bin2hex(random_bytes(4)), 0, 8));
            $entry->setStatus(ChronicleStatus::Draft);
            $entry->setSortOrder(1000 + $i);
            $em->persist($entry);
            $made[] = $entry;
        }
        $em->flush();

        $ids = array_map(static fn (ChronicleEntry $e): int => (int) $e->getId(), $made);
        $visible = [$ids[2], $ids[0], $ids[1]];

        $client->request('GET', '/admin/chronicle-entry');
        while ($client->getResponse()->isRedirection()) {
            $loc = (string) $client->getResponse()->headers->get('Location');
            if (str_contains($loc, '/admin/login')) {
                self::fail('Still redirected to login after form auth; location='.$loc);
            }
            $client->followRedirect();
        }

        $html = (string) $client->getResponse()->getContent();
        self::assertResponseIsSuccessful();

        if (!preg_match('/name="chronicle-reorder-csrf" content="([^"]+)"/', $html, $m)) {
            self::fail('Missing chronicle-reorder-csrf meta');
        }
        $csrf = html_entity_decode($m[1], ENT_QUOTES);

        $client->request(
            'POST',
            '/admin/chronicle/reorder',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode(['ids' => $visible, '_token' => $csrf], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertTrue($payload['ok'] ?? false);

        $em->clear();
        $orders = [];
        foreach ($visible as $id) {
            $orders[] = $em->find(ChronicleEntry::class, $id)?->getSortOrder();
        }
        self::assertNotNull($orders[0]);
        self::assertNotNull($orders[1]);
        self::assertNotNull($orders[2]);
        self::assertLessThan($orders[1], $orders[0]);
        self::assertLessThan($orders[2], $orders[1]);
    }
}
