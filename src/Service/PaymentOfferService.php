<?php

namespace App\Service;

use App\Entity\Inquiry;
use App\Entity\PaymentOffer;
use App\Enum\PaymentOfferStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PaymentOfferService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TelegramNotifier $telegramNotifier,
    ) {
    }

    public function createForInquiry(
        Inquiry $inquiry,
        string $title,
        int $amountKopecks,
        ?string $sberPaymentUrl = null,
        ?string $note = null,
        ?\DateTimeImmutable $expiresAt = null,
    ): PaymentOffer {
        $offer = (new PaymentOffer())
            ->setInquiry($inquiry)
            ->setTitle($title)
            ->setAmount($amountKopecks)
            ->setSberPaymentUrl($sberPaymentUrl)
            ->setNote($note)
            ->setStatus(PaymentOfferStatus::Pending);

        if ($expiresAt instanceof \DateTimeImmutable) {
            $offer->setExpiresAt($expiresAt);
        }

        $this->entityManager->persist($offer);
        $this->entityManager->flush();

        $paymentUrl = $this->urlGenerator->generate(
            'web_payment_show',
            ['token' => $offer->getToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $this->telegramNotifier->notifyPaymentOffer($offer, $paymentUrl);

        return $offer;
    }

    public function getValidOffer(string $token): PaymentOffer
    {
        $offer = $this->entityManager->getRepository(PaymentOffer::class)->findOneBy(['token' => $token]);

        if (!$offer instanceof PaymentOffer) {
            throw new NotFoundHttpException('Ссылка на оплату не найдена.');
        }

        if ($offer->isExpired() && PaymentOfferStatus::Pending === $offer->getStatus()) {
            $offer->setStatus(PaymentOfferStatus::Expired);
            $this->entityManager->flush();
        }

        if (PaymentOfferStatus::Expired === $offer->getStatus()) {
            throw new NotFoundHttpException('Срок действия ссылки истёк.');
        }

        return $offer;
    }

    public function markAsPaid(PaymentOffer $offer): void
    {
        $offer
            ->setStatus(PaymentOfferStatus::Paid)
            ->setPaidAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }
}
