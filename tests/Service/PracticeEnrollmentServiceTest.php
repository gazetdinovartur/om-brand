<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\InquiryStatus;
use App\Enum\PaymentOfferStatus;
use App\Infrastructure\Yookassa\YookassaClient;
use App\Practice\PracticeFormat;
use App\Repository\PaymentOfferRepository;
use App\Repository\SiteUserRepository;
use App\Service\PracticeEnrollmentService;
use App\Tests\Support\PracticePaymentFixtures;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PracticeEnrollmentServiceTest extends KernelTestCase
{
    use PracticePaymentFixtures;

    public function testPaidEnrollmentCreatesYookassaOffer(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $paymentId = $this->uniqueYookassaPaymentId('enroll-paid');
        $container->set(YookassaClient::class, $this->mockYookassaClient([
            $this->yookassaCreateResponse($paymentId, 'https://yookassa.test/enroll-paid'),
        ]));

        /** @var PracticeEnrollmentService $enrollment */
        $enrollment = $container->get(PracticeEnrollmentService::class);
        $email = 'enroll-paid-'.bin2hex(random_bytes(3)).'@example.test';

        $result = $enrollment->enroll(
            name: 'Аня',
            email: $email,
            format: PracticeFormat::Single,
            message: 'Хочу разовую встречу',
        );

        self::assertNotNull($result['offer']);
        self::assertSame(PaymentOfferStatus::Pending, $result['offer']->getStatus());
        self::assertSame(222_00, $result['offer']->getAmount());
        self::assertSame($paymentId, $result['offer']->getYookassaPaymentId());
        self::assertSame('https://yookassa.test/enroll-paid', $result['offer']->getPaymentUrl());
        self::assertSame('single', $result['offer']->getPracticeFormat());
        self::assertSame(1, $result['offer']->getSessionsTotal());
        self::assertSame(1, $result['offer']->getSessionsLeft());
        self::assertNotSame('', $result['successMessage']);
        // Пока не оплачено — заявка остаётся новой.
        self::assertSame(InquiryStatus::New, $result['inquiry']->getStatus());

        $siteUser = $container->get(SiteUserRepository::class)->findOneBy(['email' => $email]);
        self::assertNotNull($siteUser);
        self::assertSame($siteUser->getId(), $result['offer']->getSiteUser()?->getId());
    }

    public function testFreeEnrollmentMarksOfferPaidWithoutYookassa(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $container->set(YookassaClient::class, $this->mockYookassaClient([]));

        /** @var PracticeEnrollmentService $enrollment */
        $enrollment = $container->get(PracticeEnrollmentService::class);
        $email = 'enroll-free-'.bin2hex(random_bytes(3)).'@example.test';

        $result = $enrollment->enroll(
            name: 'Борис',
            email: $email,
            format: PracticeFormat::First,
            message: 'Первая встреча',
        );

        self::assertNotNull($result['offer']);
        self::assertSame(PaymentOfferStatus::Paid, $result['offer']->getStatus());
        self::assertSame(0, $result['offer']->getAmount());
        self::assertNull($result['offer']->getYookassaPaymentId());
        self::assertSame('first', $result['offer']->getPracticeFormat());
        self::assertSame(1, $result['offer']->getSessionsTotal());
        self::assertSame(1, $result['offer']->getSessionsLeft());
        self::assertNotSame('', $result['successMessage']);
        self::assertSame(InquiryStatus::Done, $result['inquiry']->getStatus());

        $offerRepo = $container->get(PaymentOfferRepository::class);
        $hasAccess = $offerRepo->userHasPaidPracticeAccess($result['offer']->getSiteUser());
        self::assertTrue($hasAccess);
        self::assertTrue($offerRepo->userHasPracticeRoomAccess($result['offer']->getSiteUser(), false));
    }

    public function testCycleEnrollmentUses555Amount(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $paymentId = $this->uniqueYookassaPaymentId('enroll-cycle');
        $container->set(YookassaClient::class, $this->mockYookassaClient([
            $this->yookassaCreateResponse($paymentId, 'https://yookassa.test/cycle'),
        ]));

        /** @var PracticeEnrollmentService $enrollment */
        $enrollment = $container->get(PracticeEnrollmentService::class);

        $result = $enrollment->enroll(
            name: 'Вера',
            email: 'enroll-cycle-'.bin2hex(random_bytes(3)).'@example.test',
            format: PracticeFormat::Cycle,
            message: 'Цикл',
        );

        self::assertSame(555_00, $result['offer']?->getAmount());
        self::assertSame(PracticeFormat::Cycle->paymentTitle(), $result['offer']?->getTitle());
        self::assertSame($paymentId, $result['offer']?->getYookassaPaymentId());
        self::assertSame('cycle', $result['offer']?->getPracticeFormat());
        self::assertSame(4, $result['offer']?->getSessionsTotal());
        self::assertSame(4, $result['offer']?->getSessionsLeft());
    }
}
