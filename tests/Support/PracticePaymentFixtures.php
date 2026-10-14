<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Inquiry;
use App\Entity\PaymentOffer;
use App\Entity\PracticeSession;
use App\Entity\SiteUser;
use App\Enum\ContactType;
use App\Enum\InquiryType;
use App\Enum\PaymentOfferStatus;
use App\Enum\PracticeSessionStatus;
use App\Infrastructure\Yookassa\YookassaClient;
use App\Practice\PracticeFormat;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

trait PracticePaymentFixtures
{
    private function uniqueYookassaPaymentId(string $prefix = 'yk'): string
    {
        return $prefix.'-'.bin2hex(random_bytes(8));
    }

    private function createSiteUser(EntityManagerInterface $em, ?string $email = null): SiteUser
    {
        $email ??= 'practice-'.bin2hex(random_bytes(4)).'@example.test';
        $user = (new SiteUser())
            ->setEmail($email)
            ->setDisplayName('Practice User');
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createInquiry(
        EntityManagerInterface $em,
        string $email,
        string $name = 'Practice Applicant',
        InquiryType $type = InquiryType::Practice,
    ): Inquiry {
        $inquiry = (new Inquiry())
            ->setName($name)
            ->setContact($email)
            ->setContactType(ContactType::Email)
            ->setInquiryType($type)
            ->setMessage('Тестовая заявка на практику');
        $em->persist($inquiry);
        $em->flush();

        return $inquiry;
    }

    private function createPendingOffer(
        EntityManagerInterface $em,
        Inquiry $inquiry,
        ?SiteUser $siteUser = null,
        int $amountKopecks = 222_00,
        ?string $yookassaPaymentId = null,
        ?string $paymentUrl = null,
    ): PaymentOffer {
        $format = PracticeFormat::Single;
        if (555_00 === $amountKopecks) {
            $format = PracticeFormat::Cycle;
        } elseif (0 === $amountKopecks) {
            $format = PracticeFormat::First;
        }

        $offer = (new PaymentOffer())
            ->setInquiry($inquiry)
            ->setSiteUser($siteUser)
            ->setTitle('Регулярная практика — разовая встреча')
            ->setAmount($amountKopecks)
            ->setStatus(PaymentOfferStatus::Pending)
            ->setNote('Практика /practice · '.$format->value)
            ->applyPracticeFormat($format)
            ->setYookassaPaymentId($yookassaPaymentId)
            ->setPaymentUrl($paymentUrl ?? 'https://yoomoney.ru/checkout/payments/v2/contract?orderId=test');
        $em->persist($offer);
        $em->flush();

        return $offer;
    }

    private function createPracticeSession(
        EntityManagerInterface $em,
        string $title = 'Встреча практики',
        PracticeSessionStatus $status = PracticeSessionStatus::Scheduled,
        ?string $eventId = null,
        ?string $videoId = null,
        ?\DateTimeImmutable $startsAt = null,
    ): PracticeSession {
        $session = (new PracticeSession())
            ->setTitle($title)
            ->setStatus($status)
            ->setStartsAt($startsAt ?? new \DateTimeImmutable('+1 day'))
            ->setKinescopeEventId($eventId)
            ->setKinescopeVideoId($videoId);
        $em->persist($session);
        $em->flush();

        return $session;
    }

    /**
     * @param list<MockResponse>|callable $responses
     */
    private function mockYookassaClient(array|callable $responses): YookassaClient
    {
        return new YookassaClient(
            new MockHttpClient($responses),
            'test-shop-id',
            'test-secret-key',
        );
    }

    private function yookassaCreateResponse(
        string $paymentId,
        string $confirmationUrl = 'https://yoomoney.ru/checkout/payments/v2/contract?orderId=test',
    ): MockResponse {
        return new MockResponse(json_encode([
            'id' => $paymentId,
            'status' => 'pending',
            'confirmation' => [
                'type' => 'redirect',
                'confirmation_url' => $confirmationUrl,
            ],
        ], \JSON_THROW_ON_ERROR), ['http_code' => 200]);
    }

    private function yookassaVerifyResponse(string $status, string $paymentId): MockResponse
    {
        return new MockResponse(json_encode([
            'id' => $paymentId,
            'status' => $status,
            'paid' => 'succeeded' === $status,
        ], \JSON_THROW_ON_ERROR), ['http_code' => 200]);
    }
}
