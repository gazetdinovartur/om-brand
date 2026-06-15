<?php

namespace App\Controller;

use App\Service\PaymentOfferService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PaymentController extends AbstractController
{
    #[Route('/oplata/{token}', name: 'web_payment_show', methods: ['GET'])]
    public function show(string $token, PaymentOfferService $paymentOfferService): Response
    {
        $offer = $paymentOfferService->getValidOffer($token);

        return $this->render('web/payment/show.html.twig', [
            'offer' => $offer,
            'inquiry' => $offer->getInquiry(),
        ]);
    }
}
