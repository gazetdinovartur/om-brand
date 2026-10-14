<?php

namespace App\Controller;

use App\Enum\PaymentOfferStatus;
use App\Service\PaymentOfferService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Legacy /oplata/{token}: thin redirect to gateway URL when set. */
final class PaymentController extends AbstractController
{
    #[Route('/oplata/{token}', name: 'web_payment_show', methods: ['GET'])]
    public function show(
        string $token,
        PaymentOfferService $paymentOfferService,
    ): Response {
        $offer = $paymentOfferService->getValidOffer($token);

        if (PaymentOfferStatus::Paid === $offer->getStatus()) {
            return $this->redirectToRoute('web_account', ['tab' => 'practice']);
        }

        $payUrl = $paymentOfferService->getPayUrl($offer);
        if (null !== $payUrl) {
            return $this->redirect($payUrl);
        }

        throw $this->createNotFoundException('Ссылка на оплату ещё не готова.');
    }
}
