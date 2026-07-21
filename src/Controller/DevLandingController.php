<?php

namespace App\Controller;

use App\Content\LandingContent;
use App\Form\InquiryFormType;
use App\Repository\CaseStudyRepository;
use App\Service\InquiryService;
use App\Service\PublicSiteContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class DevLandingController extends AbstractController
{
    use InquiryFormHandlerTrait;

    #[Route('/dev--null', name: 'web_dev_landing', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        PublicSiteContext $siteContext,
        CaseStudyRepository $caseStudyRepository,
        InquiryService $inquiryService,
        #[Autowire('@limiter.inquiry_form')]
        RateLimiterFactory $inquiryLimiterFactory,
    ): Response {
        $settings = $siteContext->getSettings();
        $blocksBySlug = $siteContext->getBlocksBySlug();
        $cases = $caseStudyRepository->findLandingOrdered();
        $hasAnyCases = $caseStudyRepository->countPublished() > 0;
        $form = $this->createForm(InquiryFormType::class);
        $form->handleRequest($request);

        $response = $this->handleInquirySubmission(
            $request,
            $form,
            $inquiryService,
            $inquiryLimiterFactory,
            $siteContext,
            'web_dev_landing',
        );
        if (null !== $response) {
            return $response;
        }

        return $this->render('web/dev/index.html.twig', [
            'settings' => $settings,
            'blocks' => $siteContext->getVisibleBlocks(),
            'blocksBySlug' => $blocksBySlug,
            'cases' => $cases,
            'hasAnyCases' => $hasAnyCases,
            'navAnchors' => LandingContent::navigationAnchors($hasAnyCases),
            'form' => $form,
        ]);
    }
}
