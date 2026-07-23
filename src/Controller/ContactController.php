<?php

namespace App\Controller;

use App\Content\HouseContent;
use App\Form\ContactFormType;
use App\Service\InquiryService;
use App\Service\PublicSiteContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    use InquiryFormHandlerTrait;

    #[Route('/contact', name: 'web_contact', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        PublicSiteContext $siteContext,
        InquiryService $inquiryService,
        #[Autowire('@limiter.inquiry_form')]
        RateLimiterFactory $inquiryLimiterFactory,
    ): Response {
        $settings = $siteContext->getSettings();
        $form = $this->createForm(ContactFormType::class);
        $form->handleRequest($request);

        $response = $this->handleInquirySubmission(
            $request,
            $form,
            $inquiryService,
            $inquiryLimiterFactory,
            $siteContext,
            'web_contact',
        );
        if (null !== $response) {
            return $response;
        }

        return $this->render('web/contact/index.html.twig', [
            'settings' => $settings,
            'form' => $form,
            'pageTitle' => HouseContent::contactPageTitle(),
            'pageLead' => HouseContent::contactPageLead(),
        ]);
    }
}
