<?php

namespace App\Controller;

use App\Repository\SiteSettingsRepository;
use App\Service\PaymentOfferService;
use App\Seo\SeoMetadataFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PaymentController extends AbstractController
{
    #[Route('/oplata/{token}', name: 'web_payment_show', methods: ['GET'])]
    public function show(
        string $token,
        Request $request,
        PaymentOfferService $paymentOfferService,
        SeoMetadataFactory $seoMetadataFactory,
        SiteSettingsRepository $siteSettingsRepository,
    ): Response {
        $offer = $paymentOfferService->getValidOffer($token);
        $settings = $siteSettingsRepository->getSettings();

        return $this->render('web/payment/show.html.twig', [
            'offer' => $offer,
            'inquiry' => $offer->getInquiry(),
            'seo' => $seoMetadataFactory->forRoute(
                'web_payment_show',
                $request,
                $settings,
                [],
                $offer->getTitle(),
            ),
        ]);
    }
}
