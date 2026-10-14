<?php

namespace App\EventSubscriber;

use App\Entity\PaymentOffer;
use App\Service\PaymentOfferService;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Telegram-уведомление после успешного создания оплаты в админке.
 * Платёж ЮKassa создаётся в PaymentOfferCrudController::persistEntity.
 */
final class PaymentOfferAdminSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PaymentOfferService $paymentOfferService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterEntityPersistedEvent::class => 'notifyCreated',
        ];
    }

    public function notifyCreated(AfterEntityPersistedEvent $event): void
    {
        $entity = $event->getEntityInstance();
        if (!$entity instanceof PaymentOffer) {
            return;
        }

        // Без URL значит persistEntity откатил создание (или ещё не прикрепил ЮKassa).
        if (null === $this->paymentOfferService->getPayUrl($entity)) {
            return;
        }

        $this->paymentOfferService->notifyCreated($entity);
    }
}
