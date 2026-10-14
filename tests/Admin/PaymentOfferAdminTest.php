<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\AdminUser;
use App\Entity\PaymentOffer;
use App\Enum\PaymentOfferStatus;
use App\Infrastructure\Yookassa\YookassaClient;
use App\Repository\PaymentOfferRepository;
use App\Tests\Support\PracticePaymentFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PaymentOfferAdminTest extends WebTestCase
{
    use PracticePaymentFixtures;

    public function testIndexHidesRawPaymentUrlColumn(): void
    {
        $client = $this->loginAsAdmin(static::createClient());
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $inquiry = $this->createInquiry($em, 'pay-index-'.bin2hex(random_bytes(3)).'@example.test');
        $this->createPendingOffer(
            $em,
            $inquiry,
            paymentUrl: 'https://yookassa.test/should-not-appear-as-column-'.bin2hex(random_bytes(2)),
        );

        $html = $this->fetchAdminHtml($client, '/admin/payment-offer');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Оплаты', $html);
        self::assertStringContainsString('Создать оплату', $html);
        // Колонка убрана с index; сырой URL не должен торчать в таблице.
        self::assertStringNotContainsString('>Ссылка на оплату<', $html);
        self::assertStringNotContainsString('yookassa.test/should-not-appear-as-column', $html);
    }

    public function testNewFormPrefillsInquiryAndOmitsEditableStatus(): void
    {
        $client = $this->loginAsAdmin(static::createClient());
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $inquiry = $this->createInquiry($em, 'pay-prefill-'.bin2hex(random_bytes(3)).'@example.test', 'Имя Префилл');

        $html = $this->fetchAdminHtml($client, '/admin/payment-offer/new?inquiry='.$inquiry->getId());
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Оплата — Имя Префилл', $html);
        self::assertStringContainsString('ЮKassa', $html);
        self::assertStringNotContainsString('СБП пересчитается', $html);
        // Статус скрыт на форме — нет select/input name со status.
        self::assertDoesNotMatchRegularExpression('/name="[^"]*\[status\]"/', $html);
    }

    public function testAdminCreateAttachesYookassaPayment(): void
    {
        $client = static::createClient();
        // Иначе reboot между login/GET/POST сбросит мок YooKassa.
        $client->disableReboot();
        $paymentId = $this->uniqueYookassaPaymentId('admin-create');
        // До любого HTTP: EventDispatcher поднимает PaymentOfferAdminSubscriber → YookassaClient.
        static::getContainer()->set(YookassaClient::class, $this->mockYookassaClient([
            $this->yookassaCreateResponse($paymentId, 'https://yookassa.test/admin-pay'),
        ]));
        $this->loginAsAdmin($client);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $inquiry = $this->createInquiry($em, 'pay-create-'.bin2hex(random_bytes(3)).'@example.test', 'Админ Создание');

        $crawler = $client->request('GET', '/admin/payment-offer/new?inquiry='.$inquiry->getId());
        while ($client->getResponse()->isRedirection()) {
            $client->followRedirect();
            $crawler = $client->getCrawler();
        }
        self::assertResponseIsSuccessful();

        $button = $crawler->selectButton('Создать');
        if (0 === $button->count()) {
            $button = $crawler->selectButton('Сохранить');
        }
        self::assertGreaterThan(0, $button->count(), 'Create/save button not found on payment offer form');

        $form = $button->form();
        $values = $this->withPaymentOfferFormValues($form->getPhpValues(), [
            'title' => 'Счёт из админки',
            'amount' => '1500',
            'inquiry' => (string) $inquiry->getId(),
        ]);

        $client->request($form->getMethod(), $form->getUri(), $values);
        while ($client->getResponse()->isRedirection()) {
            $client->followRedirect();
        }

        self::assertResponseIsSuccessful();
        $offers = static::getContainer()->get(PaymentOfferRepository::class)
            ->findBy(['inquiry' => $inquiry], ['id' => 'DESC'], 1);
        self::assertNotEmpty($offers);
        /** @var PaymentOffer $offer */
        $offer = $offers[0];
        self::assertSame(PaymentOfferStatus::Pending, $offer->getStatus());
        self::assertSame($paymentId, $offer->getYookassaPaymentId());
        self::assertSame('https://yookassa.test/admin-pay', $offer->getPaymentUrl());
        self::assertSame(1500_00, $offer->getAmount());
        self::assertSame('Счёт из админки', $offer->getTitle());
    }

    public function testAdminCreateRollsBackWhenYookassaFails(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        static::getContainer()->set(
            YookassaClient::class,
            new YookassaClient(new MockHttpClient([
                new MockResponse('{"type":"error","description":"boom"}', ['http_code' => 500]),
            ]), 'test-shop-id', 'test-secret-key'),
        );
        $this->loginAsAdmin($client);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $inquiry = $this->createInquiry($em, 'pay-fail-'.bin2hex(random_bytes(3)).'@example.test');
        $before = \count(static::getContainer()->get(PaymentOfferRepository::class)->findBy(['inquiry' => $inquiry]));

        $crawler = $client->request('GET', '/admin/payment-offer/new?inquiry='.$inquiry->getId());
        while ($client->getResponse()->isRedirection()) {
            $client->followRedirect();
            $crawler = $client->getCrawler();
        }

        $button = $crawler->selectButton('Создать');
        if (0 === $button->count()) {
            $button = $crawler->selectButton('Сохранить');
        }
        $form = $button->form();
        $values = $this->withPaymentOfferFormValues($form->getPhpValues(), [
            'title' => 'Не должен сохраниться',
            'amount' => '999',
            'inquiry' => (string) $inquiry->getId(),
        ]);

        try {
            $client->request($form->getMethod(), $form->getUri(), $values);
        } catch (\Throwable) {
            // RuntimeException из persistEntity допустим.
        }

        $after = \count(static::getContainer()->get(PaymentOfferRepository::class)->findBy(['inquiry' => $inquiry]));
        self::assertSame($before, $after, 'Failed YooKassa create must not leave PaymentOffer in DB');
    }

    public function testDetailShowsGatewayLinkBlock(): void
    {
        $client = $this->loginAsAdmin(static::createClient());
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $inquiry = $this->createInquiry($em, 'pay-detail-'.bin2hex(random_bytes(3)).'@example.test');
        $offer = $this->createPendingOffer(
            $em,
            $inquiry,
            paymentUrl: 'https://yookassa.test/detail-gateway',
            yookassaPaymentId: $this->uniqueYookassaPaymentId('detail'),
        );

        $html = $this->fetchAdminHtml($client, '/admin/payment-offer/'.$offer->getId());
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Ссылка шлюза', $html);
        self::assertStringContainsString('https://yookassa.test/detail-gateway', $html);
        self::assertStringContainsString('Ссылка для клиента', $html);
    }

    private function loginAsAdmin(KernelBrowser $client): KernelBrowser
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $admin = $em->getRepository(AdminUser::class)->findOneBy(['email' => 'payment-admin-test@localhost']);
        if (!$admin instanceof AdminUser) {
            $admin = (new AdminUser())->setEmail('payment-admin-test@localhost');
            $em->persist($admin);
        }
        $admin->setPassword($hasher->hashPassword($admin, 'TestAdmin1!'));
        $em->flush();

        $crawler = $client->request('GET', '/admin/login');
        $form = $crawler->selectButton('Войти')->form([
            '_username' => 'payment-admin-test@localhost',
            '_password' => 'TestAdmin1!',
        ]);
        $client->submit($form);
        if ($client->getResponse()->isRedirection()) {
            $client->followRedirect();
        }

        return $client;
    }

    private function fetchAdminHtml(KernelBrowser $client, string $url): string
    {
        $client->request('GET', $url);
        while ($client->getResponse()->isRedirection()) {
            $loc = (string) $client->getResponse()->headers->get('Location');
            if (str_contains($loc, '/admin/login')) {
                self::fail('Redirected to login: '.$loc);
            }
            $client->followRedirect();
        }

        return (string) $client->getResponse()->getContent();
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $fields
     *
     * @return array<string, mixed>
     */
    private function withPaymentOfferFormValues(array $values, array $fields): array
    {
        $root = null;
        foreach ($values as $key => $payload) {
            if (\is_array($payload) && (\array_key_exists('title', $payload) || \array_key_exists('amount', $payload))) {
                $root = $key;
                break;
            }
        }
        self::assertNotNull($root, 'PaymentOffer form root not found in '.implode(', ', array_keys($values)));

        foreach ($fields as $name => $value) {
            $values[$root][$name] = $value;
        }

        return $values;
    }
}
