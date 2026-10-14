<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\Entity\PaymentOffer;
use App\Enum\PaymentOfferStatus;
use App\EventSubscriber\PaymentOfferAdminSubscriber;
use App\Service\PaymentOfferService;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use PHPUnit\Framework\TestCase;

final class PaymentOfferAdminSubscriberTest extends TestCase
{
    public function testNotifyCreatedSkippedWithoutPayUrl(): void
    {
        $offer = (new PaymentOffer())
            ->setTitle('Без URL')
            ->setAmount(100_00)
            ->setStatus(PaymentOfferStatus::Pending);

        $service = $this->createMock(PaymentOfferService::class);
        $service->expects(self::once())->method('getPayUrl')->with($offer)->willReturn(null);
        $service->expects(self::never())->method('notifyCreated');

        $subscriber = new PaymentOfferAdminSubscriber($service);
        $subscriber->notifyCreated(new AfterEntityPersistedEvent($offer));
    }

    public function testNotifyCreatedWhenPayUrlPresent(): void
    {
        $offer = (new PaymentOffer())
            ->setTitle('С URL')
            ->setAmount(100_00)
            ->setStatus(PaymentOfferStatus::Pending)
            ->setPaymentUrl('https://yookassa.test/pay');

        $service = $this->createMock(PaymentOfferService::class);
        $service->expects(self::once())->method('getPayUrl')->with($offer)->willReturn('https://yookassa.test/pay');
        $service->expects(self::once())->method('notifyCreated')->with($offer);

        $subscriber = new PaymentOfferAdminSubscriber($service);
        $subscriber->notifyCreated(new AfterEntityPersistedEvent($offer));
    }

    public function testIgnoresNonPaymentOfferEntities(): void
    {
        $service = $this->createMock(PaymentOfferService::class);
        $service->expects(self::never())->method('getPayUrl');
        $service->expects(self::never())->method('notifyCreated');

        $subscriber = new PaymentOfferAdminSubscriber($service);
        $subscriber->notifyCreated(new AfterEntityPersistedEvent(new \stdClass()));
    }
}
