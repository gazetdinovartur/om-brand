<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Entity\AdminUser;
use App\Entity\Inquiry;
use App\Enum\InquiryStatus;
use App\Tests\Support\PracticePaymentFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class InquiryAdminTest extends WebTestCase
{
    use PracticePaymentFixtures;

    public function testIndexShowsCloseAndInvoiceActionsAndSimplifiedStatuses(): void
    {
        $client = $this->loginAsAdmin();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $inquiry = $this->createInquiry($em, 'inquiry-admin-'.bin2hex(random_bytes(3)).'@example.test', 'Клиент Для Счёта');

        $html = $this->fetchAdminHtml($client, '/admin/inquiry');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Закрыть', $html);
        self::assertStringContainsString('Выставить счёт', $html);
        self::assertStringContainsString((string) $inquiry->getId(), $html);
        self::assertStringContainsString('Клиент Для Счёта', $html);
        self::assertStringNotContainsString('В работе', $html);
        self::assertStringNotContainsString('В архиве', $html);
        self::assertStringContainsString('/admin/payment-offer/new', $html);
        self::assertStringContainsString('inquiry='.(string) $inquiry->getId(), $html);
    }

    public function testCloseActionMarksInquiryDone(): void
    {
        $client = $this->loginAsAdmin();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $inquiry = $this->createInquiry($em, 'close-'.bin2hex(random_bytes(3)).'@example.test');
        self::assertSame(InquiryStatus::New, $inquiry->getStatus());

        $client->request('GET', '/admin/inquiry/'.$inquiry->getId().'/close');
        while ($client->getResponse()->isRedirection()) {
            $client->followRedirect();
        }
        self::assertResponseIsSuccessful();

        $em->clear();
        $fresh = $em->find(Inquiry::class, $inquiry->getId());
        self::assertSame(InquiryStatus::Done, $fresh?->getStatus());

        $html = $this->fetchAdminHtml($client, '/admin/inquiry');
        // У закрытой нет кнопки «Закрыть» на этой строке — действие displayIf(New).
        self::assertStringNotContainsString(
            '/admin/inquiry/'.$inquiry->getId().'/close',
            $html,
        );
    }

    public function testEditFormOnlyOffersNewAndClosedStatuses(): void
    {
        $client = $this->loginAsAdmin();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $inquiry = $this->createInquiry($em, 'edit-status-'.bin2hex(random_bytes(3)).'@example.test');

        $html = $this->fetchAdminHtml($client, '/admin/inquiry/'.$inquiry->getId().'/edit');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Новая', $html);
        self::assertStringContainsString('Закрыта', $html);
        self::assertStringNotContainsString('value="in_progress"', $html);
        self::assertStringNotContainsString('value="archived"', $html);
        self::assertStringNotContainsString('Завершена', $html);
    }

    public function testStatusFilterIsConfigured(): void
    {
        $client = $this->loginAsAdmin();

        $indexHtml = $this->fetchAdminHtml($client, '/admin/inquiry');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('modal-filters', $indexHtml);

        $filtersHtml = $this->fetchAdminHtml($client, '/admin/inquiry/render-filters');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Новая', $filtersHtml);
        self::assertStringContainsString('Закрыта', $filtersHtml);
        self::assertStringNotContainsString('in_progress', $filtersHtml);
        self::assertStringNotContainsString('archived', $filtersHtml);
    }

    private function loginAsAdmin(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $admin = $em->getRepository(AdminUser::class)->findOneBy(['email' => 'inquiry-admin-test@localhost']);
        if (!$admin instanceof AdminUser) {
            $admin = (new AdminUser())->setEmail('inquiry-admin-test@localhost');
            $em->persist($admin);
        }
        $admin->setPassword($hasher->hashPassword($admin, 'TestAdmin1!'));
        $em->flush();

        $crawler = $client->request('GET', '/admin/login');
        $form = $crawler->selectButton('Войти')->form([
            '_username' => 'inquiry-admin-test@localhost',
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
}
