<?php

namespace App\Service;

use App\Entity\Inquiry;
use App\Entity\PaymentOffer;
use App\Entity\SiteUser;
use App\Enum\InquiryStatus;
use App\Enum\InquiryType;
use App\Enum\PaymentOfferStatus;
use App\Infrastructure\Yookassa\YookassaClient;
use App\Practice\PracticeFormat;
use App\Repository\PaymentOfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PaymentOfferService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentOfferRepository $offers,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TelegramNotifier $telegramNotifier,
        private readonly YookassaClient $yookassaClient,
        private readonly PracticePaidMailNotifier $paidMailNotifier,
        private readonly SiteUserTelegramNotifier $userTelegramNotifier,
        private readonly PublicSiteContext $siteContext,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function createForInquiry(
        Inquiry $inquiry,
        string $title,
        int $amountKopecks,
        ?string $paymentUrl = null,
        ?string $note = null,
        ?\DateTimeImmutable $expiresAt = null,
        ?SiteUser $siteUser = null,
        bool $notifyTelegram = true,
        bool $markPaid = false,
        bool $createYookassaPayment = false,
        ?string $payerEmail = null,
        ?PracticeFormat $practiceFormat = null,
    ): PaymentOffer {
        $offer = (new PaymentOffer())
            ->setInquiry($inquiry)
            ->setSiteUser($siteUser)
            ->setTitle($title)
            ->setAmount($amountKopecks)
            ->setNote($note)
            ->setStatus($markPaid ? PaymentOfferStatus::Paid : PaymentOfferStatus::Pending);

        if ($practiceFormat instanceof PracticeFormat) {
            $offer->applyPracticeFormat($practiceFormat);
        }

        if ($markPaid) {
            $offer->setPaidAt(new \DateTimeImmutable());
            if (
                InquiryType::Practice === $inquiry->getInquiryType()
                && InquiryStatus::New === $inquiry->getStatus()
            ) {
                $inquiry->setStatus(InquiryStatus::Done);
            }
        }

        if ($expiresAt instanceof \DateTimeImmutable) {
            $offer->setExpiresAt($expiresAt);
        }

        // Новые оплаты без ЮKassa (бесплатные) не получают СБП-ссылку.
        // Legacy SBP только если явно передан paymentUrl.
        if (!$createYookassaPayment && null !== $paymentUrl) {
            $offer->setPaymentUrl($paymentUrl);
        }

        $this->entityManager->persist($offer);
        $this->entityManager->flush();

        if ($createYookassaPayment && !$markPaid && $amountKopecks > 0) {
            $this->attachYookassaPayment($offer, $payerEmail ?? $inquiry->getContact(), $title);
        }

        if ($notifyTelegram && !$markPaid) {
            $this->notifyCreated($offer);
        }

        return $offer;
    }

    public function attachYookassaPayment(PaymentOffer $offer, string $payerEmail, ?string $description = null): void
    {
        if (!$this->yookassaClient->isConfigured()) {
            throw new \RuntimeException('YooKassa is not configured (YOOKASSA_SHOP_ID / YOOKASSA_SECRET_KEY).');
        }

        $email = mb_strtolower(trim($payerEmail));
        if ('' === $email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Valid payer email is required for YooKassa.');
        }

        $amountRubles = (int) round($offer->getAmountRubles());
        if ($amountRubles < 1) {
            throw new \InvalidArgumentException('YooKassa amount must be at least 1 ₽.');
        }

        $returnUrl = $this->urlGenerator->generate(
            'web_account',
            [
                'tab' => 'practice',
                'yk_return' => '1',
                'token' => $offer->getToken(),
            ],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $result = $this->yookassaClient->createPayment(
            email: $email,
            amountRubles: $amountRubles,
            description: $description ?? $offer->getTitle(),
            returnUrl: $returnUrl,
            metadata: [
                'offer_token' => $offer->getToken(),
                'offer_id' => (string) ($offer->getId() ?? ''),
            ],
        );

        $offer
            ->setYookassaPaymentId($result->paymentId)
            ->setPaymentUrl($result->gatewayUrl);
        $this->entityManager->flush();
    }

    public function notifyCreated(PaymentOffer $offer): void
    {
        $payUrl = $offer->getPaymentUrl() ?: $this->getLegacyClientUrl($offer);
        $this->telegramNotifier->notifyPaymentOffer($offer, $payUrl);
    }

    /** Direct gateway URL when set; otherwise legacy /oplata page. */
    public function getPayUrl(PaymentOffer $offer): ?string
    {
        $url = trim((string) $offer->getPaymentUrl());

        return '' !== $url ? $url : null;
    }

    public function getLegacyClientUrl(PaymentOffer $offer): string
    {
        return $this->urlGenerator->generate(
            'web_payment_show',
            ['token' => $offer->getToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    /** @deprecated prefer getPayUrl() for YooKassa direct link */
    public function getClientUrl(PaymentOffer $offer): string
    {
        return $this->getPayUrl($offer) ?? $this->getLegacyClientUrl($offer);
    }

    public function getValidOffer(string $token): PaymentOffer
    {
        $offer = $this->offers->findOneBy(['token' => $token]);

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

    /**
     * Idempotent: pending → paid once. Sends room email + Telegram on transition.
     * Practice-заявку закрывает автоматически.
     */
    public function markAsPaid(PaymentOffer $offer): bool
    {
        if (PaymentOfferStatus::Paid === $offer->getStatus()) {
            return false;
        }

        $offer
            ->setStatus(PaymentOfferStatus::Paid)
            ->setPaidAt(new \DateTimeImmutable());

        $inquiry = $offer->getInquiry();
        if (
            $inquiry instanceof Inquiry
            && InquiryType::Practice === $inquiry->getInquiryType()
            && InquiryStatus::New === $inquiry->getStatus()
        ) {
            $inquiry->setStatus(InquiryStatus::Done);
        }

        $this->entityManager->flush();

        $this->paidMailNotifier->sendRoomAccess($offer, $this->siteContext->getSettings());
        $this->telegramNotifier->notifyPaymentPaid($offer);
        $siteUser = $offer->getSiteUser();
        if ($siteUser instanceof SiteUser) {
            $this->userTelegramNotifier->notifyPaid($siteUser, $offer);
        }

        return true;
    }

    public function markAsCancelled(PaymentOffer $offer): void
    {
        if (PaymentOfferStatus::Paid === $offer->getStatus()) {
            return;
        }

        $offer->setStatus(PaymentOfferStatus::Cancelled);
        $this->entityManager->flush();
    }

    /** Убрать офер из кабинета пользователя (Inquiry в админке остаётся). */
    public function unlinkFromSiteUser(PaymentOffer $offer): void
    {
        $offer->setSiteUser(null);
        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $webhookPayload
     */
    public function handleYookassaWebhook(array $webhookPayload): void
    {
        $object = $webhookPayload['object'] ?? null;
        if (!\is_array($object)) {
            return;
        }

        $event = (string) ($webhookPayload['event'] ?? '');
        $paymentId = str_contains($event, 'refund')
            ? trim((string) ($object['payment_id'] ?? ''))
            : trim((string) ($object['id'] ?? ''));

        if ('' === $paymentId) {
            return;
        }

        $this->syncFromYookassa($paymentId);
    }

    public function syncFromYookassa(string $yookassaPaymentId): ?PaymentOffer
    {
        $offer = $this->offers->findOneByYookassaPaymentId($yookassaPaymentId);
        if (!$offer instanceof PaymentOffer) {
            $this->logger->info('YooKassa payment not linked to local offer.', [
                'yookassa_payment_id' => $yookassaPaymentId,
            ]);

            return null;
        }

        try {
            $verified = $this->yookassaClient->verifyPayment($yookassaPaymentId);
        } catch (\Throwable $exception) {
            $this->logger->error('YooKassa verify failed.', [
                'yookassa_payment_id' => $yookassaPaymentId,
                'exception' => $exception->getMessage(),
            ]);

            return $offer;
        }

        $status = (string) ($verified['status'] ?? '');
        if ('succeeded' === $status) {
            $this->markAsPaid($offer);
        } elseif ('canceled' === $status) {
            $this->markAsCancelled($offer);
        }

        return $offer;
    }

    public function syncOfferFromYookassaIfPending(PaymentOffer $offer): PaymentOffer
    {
        if (PaymentOfferStatus::Pending !== $offer->getStatus()) {
            return $offer;
        }

        $paymentId = trim((string) $offer->getYookassaPaymentId());
        if ('' === $paymentId) {
            return $offer;
        }

        return $this->syncFromYookassa($paymentId) ?? $offer;
    }
}
