<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\WebauthnCredential;
use App\Tests\Support\SiteAuthFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class AccountKeysControllerTest extends WebTestCase
{
    use SiteAuthFixtures;

    public function testKeysTabShowsEmptyStateAndRegisterHook(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->loginViaEmailCode($client, $em, targetPath: '/account?tab=keys');
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-passkey-register]');
        self::assertSelectorExists('[data-passkey-register-button]');
        self::assertStringContainsString('Ключей пока нет', (string) $client->getResponse()->getContent());
    }

    public function testKeysTabListsExistingPasskey(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->loginViaEmailCode($client, $em, targetPath: '/account?tab=keys');
        $this->createPasskeyForUser($em, $user, 'MacBook Face ID');
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('MacBook Face ID', (string) $client->getResponse()->getContent());
        self::assertSelectorExists('form.library-account__rename');
    }

    public function testRenamePasskeyUpdatesLabel(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->loginViaEmailCode($client, $em, targetPath: '/account?tab=keys');
        $credential = $this->createPasskeyForUser($em, $user, 'Старое имя');
        $crawler = $client->followRedirect();

        $form = $this->renameForm($crawler, $credential->getId());
        $client->submit($form, [
            'label' => 'Новое имя ключа',
        ]);

        self::assertResponseRedirects('/account?tab=keys');
        $client->followRedirect();
        self::assertStringContainsString('Новое имя ключа', (string) $client->getResponse()->getContent());

        $em->clear();
        $fresh = $em->find(WebauthnCredential::class, $credential->getId());
        self::assertSame('Новое имя ключа', $fresh?->getLabel());
    }

    public function testDeletePasskeyRemovesCredential(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->loginViaEmailCode($client, $em, targetPath: '/account?tab=keys');
        $credential = $this->createPasskeyForUser($em, $user, 'Удаляемый');
        $credentialId = $credential->getId();
        $crawler = $client->followRedirect();

        $formNode = $crawler->filter(sprintf('form input[name="credential_id"][value="%s"]', $credentialId))->closest('form');
        self::assertGreaterThan(0, $formNode->count());
        $deleteForm = null;
        foreach ($crawler->filter('form') as $node) {
            $f = new Crawler($node, $client->getRequest()->getUri());
            if (
                'delete' === $f->filter('input[name="action"]')->attr('value')
                && $credentialId === $f->filter('input[name="credential_id"]')->attr('value')
            ) {
                $deleteForm = $f->form();
                break;
            }
        }
        self::assertNotNull($deleteForm);
        $client->submit($deleteForm);

        self::assertResponseRedirects('/account?tab=keys');
        $em->clear();
        self::assertNull($em->find(WebauthnCredential::class, $credentialId));
    }

    public function testCannotRenameForeignPasskey(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = $this->loginViaEmailCode($client, $em, email: 'owner-key-'.bin2hex(random_bytes(3)).'@example.test');
        $foreign = $this->createPasskeyForUser($em, $owner, 'Чужой');
        $client->restart();

        $this->loginViaEmailCode($client, $em, email: 'intruder-key-'.bin2hex(random_bytes(3)).'@example.test', targetPath: '/account?tab=keys');
        $client->followRedirect();

        $client->request('POST', '/account?tab=keys', [
            'action' => 'rename',
            'credential_id' => $foreign->getId(),
            'label' => 'Взломан',
            '_token' => 'invalid',
        ], server: ['REMOTE_ADDR' => $this->uniqueClientIp()]);

        // Чужой credential_id → 404; битый CSRF → 403. Оба недопустимы для смены.
        self::assertContains($client->getResponse()->getStatusCode(), [403, 404]);
        $em->clear();
        $fresh = $em->find(WebauthnCredential::class, $foreign->getId());
        self::assertSame('Чужой', $fresh?->getLabel());
    }

    private function renameForm(Crawler $crawler, string $credentialId): \Symfony\Component\DomCrawler\Form
    {
        foreach ($crawler->filter('form.library-account__rename') as $node) {
            $formCrawler = new Crawler($node, 'http://localhost/account?tab=keys');
            if ($credentialId === $formCrawler->filter('input[name="credential_id"]')->attr('value')) {
                return $formCrawler->form();
            }
        }

        self::fail('Rename form for credential not found.');
    }
}
