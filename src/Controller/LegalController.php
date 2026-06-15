<?php

namespace App\Controller;

use App\Content\LandingContent;
use App\Content\LegalContent;
use App\Repository\SiteSettingsRepository;
use App\Seo\SeoMetadataFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LegalController extends AbstractController
{
    #[Route('/politika-konfidencialnosti', name: 'web_privacy', methods: ['GET'])]
    public function privacy(
        Request $request,
        SiteSettingsRepository $siteSettingsRepository,
        SeoMetadataFactory $seoMetadataFactory,
    ): Response {
        $settings = $siteSettingsRepository->getSettings();
        $operatorEmail = $settings->getEmail() ?: 'указан на главной странице';

        $sections = array_map(
            static function (array $section) use ($operatorEmail): array {
                $paragraphs = array_map(
                    static fn (string $paragraph): string => strtr($paragraph, [
                        '%operator_name%' => LandingContent::personName(),
                        '%operator_email%' => $operatorEmail,
                    ]),
                    $section['paragraphs'],
                );

                return [
                    'title' => $section['title'],
                    'paragraphs' => $paragraphs,
                ];
            },
            LegalContent::privacyPolicySections(),
        );

        return $this->render('web/legal/privacy.html.twig', [
            'sections' => $sections,
            'updatedAt' => LegalContent::privacyPolicyUpdatedAt(),
            'operatorEmail' => $operatorEmail,
            'seo' => $seoMetadataFactory->forRoute(
                'web_privacy',
                $request,
                $settings,
                [],
            ),
        ]);
    }
}
