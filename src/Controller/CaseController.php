<?php

namespace App\Controller;

use App\Repository\CaseStudyRepository;
use App\Seo\SeoMetadataFactory;
use App\Service\CaseMediaEmbedFactory;
use App\Service\PublicSiteContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class CaseController extends AbstractController
{
    #[Route('/cases', name: 'web_cases', methods: ['GET'])]
    public function index(
        Request $request,
        CaseStudyRepository $caseStudyRepository,
        PublicSiteContext $siteContext,
        SeoMetadataFactory $seoMetadataFactory,
    ): Response {
        $cases = $caseStudyRepository->findPublishedOrdered();
        $settings = $siteContext->getSettings();

        return $this->render('web/cases/index.html.twig', [
            'cases' => $cases,
            'seo' => $seoMetadataFactory->forCasesIndex($request, $settings, $siteContext->getBlocksBySlugFiltered(['hero'])),
        ]);
    }

    #[Route('/cases/{slug}', name: 'web_case_show', methods: ['GET'], requirements: ['slug' => '[a-z0-9\-]+'])]
    public function show(
        string $slug,
        Request $request,
        CaseStudyRepository $caseStudyRepository,
        PublicSiteContext $siteContext,
        SeoMetadataFactory $seoMetadataFactory,
        CaseMediaEmbedFactory $caseMediaEmbedFactory,
    ): Response {
        $case = $caseStudyRepository->findPublishedBySlug($slug);
        if (null === $case || !$case->isDetailPublic()) {
            throw new NotFoundHttpException('Кейс не найден.');
        }

        $settings = $siteContext->getSettings();
        $related = array_values(array_filter(
            $caseStudyRepository->findPublishedOrdered(),
            static fn ($item): bool => $item->getId() !== $case->getId() && $item->isDetailPublic(),
        ));
        $related = \array_slice($related, 0, 3);

        return $this->render('web/cases/show.html.twig', [
            'case' => $case,
            'relatedCases' => $related,
            'videoEmbed' => $case->hasVideoPresentation() ? $caseMediaEmbedFactory->videoEmbed($case) : null,
            'audioEmbed' => $case->hasAudioPresentation() ? $caseMediaEmbedFactory->audioEmbed($case) : null,
            'omPlayerScriptUrl' => $caseMediaEmbedFactory->omPlayerScriptUrl(),
            'seo' => $seoMetadataFactory->forCaseStudy($request, $settings, $siteContext->getBlocksBySlugFiltered(['hero']), $case),
        ]);
    }
}
