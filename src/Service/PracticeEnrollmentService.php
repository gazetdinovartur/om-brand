<?php

namespace App\Service;

use App\Entity\Inquiry;
use App\Entity\PaymentOffer;
use App\Entity\SiteUser;
use App\Enum\ContactType;
use App\Enum\InquiryType;
use App\Practice\PracticeFormat;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Заявка на практику: пользователь сайта, ЮKassa, письмо / Telegram.
 *
 * @phpstan-type EnrollmentResult array{
 *     inquiry: Inquiry,
 *     offer: ?PaymentOffer,
 *     emailSent: bool,
 *     telegramSent: bool,
 *     successMessage: string
 * }
 */
final class PracticeEnrollmentService
{
    public function __construct(
        private readonly InquiryService $inquiryService,
        private readonly PaymentOfferService $paymentOfferService,
        private readonly PracticeEnrollmentMailNotifier $enrollmentMailNotifier,
        private readonly SiteUserTelegramNotifier $userTelegramNotifier,
        private readonly SiteUserResolver $siteUsers,
        private readonly PublicSiteContext $siteContext,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return EnrollmentResult
     */
    public function enroll(
        string $name,
        string $email,
        PracticeFormat $format,
        string $message,
        ?SiteUser $actor = null,
    ): array {
        $email = mb_strtolower(trim($email));
        $name = trim($name);
        $requiresPayment = $format->requiresPayment();

        if ($requiresPayment && ('' === $email || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            throw new \InvalidArgumentException('Email is required for paid practice enrollment.');
        }

        if ($actor instanceof SiteUser) {
            $siteUser = $actor;
            if ('' !== $name && $siteUser->getDisplayName() !== $name) {
                $siteUser->setDisplayName($name);
            }
            if ('' !== $email && $siteUser->getEmail() !== $email) {
                $siteUser->setEmail($email);
            }
            $this->entityManager->flush();
        } else {
            if ('' === $email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Email is required for guest practice enrollment.');
            }
            $siteUser = $this->siteUsers->findOrCreateByEmail($email, $name);
            if ($siteUser->getDisplayName() === $siteUser->getEmail() && '' !== $name) {
                $siteUser->setDisplayName($name);
                $this->entityManager->flush();
            }
        }

        [$contact, $contactType] = $this->resolveInquiryContact($siteUser, $email);

        $inquiry = $this->inquiryService->create(
            name: '' !== $name ? $name : $siteUser->getDisplayName(),
            contact: $contact,
            contactType: $contactType,
            inquiryType: InquiryType::Practice,
            message: $message,
        );

        $title = $format->paymentTitle() ?? $format->label();
        $amount = $requiresPayment ? ($format->amountKopecks() ?? 0) : 0;
        $payerEmail = '' !== $email ? $email : (string) $siteUser->getEmail();

        $offer = $this->paymentOfferService->createForInquiry(
            inquiry: $inquiry,
            title: $title,
            amountKopecks: $amount,
            note: 'Практика /practice · '.$format->value,
            siteUser: $siteUser,
            notifyTelegram: $requiresPayment,
            markPaid: !$requiresPayment,
            createYookassaPayment: $requiresPayment,
            payerEmail: $requiresPayment ? $payerEmail : null,
            practiceFormat: $format,
        );

        $emailSent = false;
        if ('' !== $payerEmail && filter_var($payerEmail, FILTER_VALIDATE_EMAIL)) {
            $emailSent = $this->enrollmentMailNotifier->send(
                $inquiry,
                $format,
                $payerEmail,
                $this->siteContext->getSettings(),
                $offer,
            );
        }

        $telegramSent = $this->userTelegramNotifier->notifyEnrollment($siteUser, $format, $offer);

        return [
            'inquiry' => $inquiry,
            'offer' => $offer,
            'emailSent' => $emailSent,
            'telegramSent' => $telegramSent,
            'successMessage' => $this->successMessage(
                $format,
                $emailSent,
                $telegramSent,
                $payerEmail,
                $actor instanceof SiteUser,
            ),
        ];
    }

    /**
     * @return array{0: string, 1: ContactType}
     */
    private function resolveInquiryContact(SiteUser $siteUser, string $email): array
    {
        if ('' !== $email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [$email, ContactType::Email];
        }

        $username = trim((string) $siteUser->getTelegramUsername());
        if ('' !== $username) {
            return ['@'.$username, ContactType::Telegram];
        }

        $telegramId = trim((string) $siteUser->getTelegramId());
        if ('' !== $telegramId) {
            return [$telegramId, ContactType::Telegram];
        }

        return [(string) $siteUser->getEmail(), ContactType::Email];
    }

    private function successMessage(
        PracticeFormat $format,
        bool $emailSent,
        bool $telegramSent,
        string $email,
        bool $authenticated,
    ): string {
        if ($emailSent) {
            if ($format->requiresPayment()) {
                return sprintf(
                    'Заявка принята. На %s отправил письмо со ссылкой на оплату%s',
                    $email,
                    $authenticated ? '. Заявку видно в Своём месте → Практика' : ' и входом в своё место',
                );
            }

            return sprintf(
                'Заявка на первую встречу принята. На %s отправил письмо%s',
                $email,
                $authenticated ? '. Заявку видно в Своём месте → Практика' : ' со входом в своё место',
            );
        }

        if ($telegramSent) {
            if ($format->requiresPayment()) {
                return 'Заявка принята. Ссылку на оплату отправил в Telegram. Также смотри Своё место → Практика';
            }

            return 'Заявка на первую встречу принята. Написал в Telegram. Доступ — в Своём месте → Практика';
        }

        if ($authenticated) {
            return 'Заявка принята. Смотри статус в Своём месте → Практика';
        }

        return 'Заявка принята, но письмо не удалось отправить. Напиши мне — пришлю ссылку вручную';
    }
}
