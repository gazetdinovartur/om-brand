<?php

namespace App\EventSubscriber;

use App\Entity\PaymentOffer;
use App\Service\PaymentOfferService;
use App\Service\PaymentSbpUrlFactory;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class PaymentOfferAdminSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PaymentSbpUrlFactory $sbpUrlFactory,
        private readonly PaymentOfferService $paymentOfferService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityPersistedEvent::class => 'applySbpUrl',
            BeforeEntityUpdatedEvent::class => 'applySbpUrl',
            AfterEntityPersistedEvent::class => 'notifyCreated',
        ];
    }

    public function applySbpUrl(BeforeEntityPersistedEvent|BeforeEntityUpdatedEvent $event): void
    {
        $entity = $event->getEntityInstance();
        if (!$entity instanceof PaymentOffer) {
            return;
        }

        $sbpUrl = $this->sbpUrlFactory->build($entity);
        if (null !== $sbpUrl) {
            $entity->setSberPaymentUrl($sbpUrl);
        }
    }

    public function notifyCreated(AfterEntityPersistedEvent $event): void
    {
        $entity = $event->getEntityInstance();
        if (!$entity instanceof PaymentOffer) {
            return;
        }

        $this->paymentOfferService->notifyCreated($entity);
    }
}
