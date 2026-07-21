<?php

namespace App\Service;

use App\Content\HouseContent;
use App\Content\LandingContent;
use App\Repository\CaseStudyRepository;
use App\Seo\SeoMetadataFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class TwigSiteGlobalsProvider
{
    /** @var list<string> */
    private const LANDING_ROUTES = ['web_dev_landing'];

    public function __construct(
        private readonly PublicSiteContext $siteContext,
        private readonly SeoMetadataFactory $seoMetadataFactory,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CaseStudyRepository $caseStudyRepository,
    ) {
    }

    public function apply(Environment $twig, Request $request, string $route = 'web_home'): void
    {
        $settings = $this->siteContext->getSettings();
        $blocksBySlug = $this->siteContext->getBlocksBySlugFiltered(['footer_hr', 'footer_excludes', 'hero']);

        $hero = $blocksBySlug['hero'] ?? null;
        $siteName = (\is_object($hero) && method_exists($hero, 'getTitle') && \is_string($hero->getTitle()) && '' !== $hero->getTitle())
            ? $hero->getTitle()
            : $settings->getName();

        $isLanding = \in_array($route, self::LANDING_ROUTES, true);
        $hasCases = $this->caseStudyRepository->countPublished() > 0;

        if ($isLanding) {
            $navItems = LandingContent::navigationAnchors($hasCases);
            $navPrefix = '';
            $fabHref = '#contact';
            $shell = 'landing';
        } else {
            $navItems = [];
            foreach (HouseContent::navigationItems() as $item) {
                $navItems[] = [
                    'href' => $this->urlGenerator->generate($item['route']),
                    'label' => $item['label'],
                    'persistFilters' => !empty($item['persistFilters']),
                ];
            }
            $navPrefix = '';
            $fabHref = $this->urlGenerator->generate('web_contact');
            $shell = 'house';
        }

        $twig->addGlobal('settings', $settings);
        $twig->addGlobal('blocksBySlug', $blocksBySlug);
        $twig->addGlobal('siteName', $siteName);
        $twig->addGlobal('legalName', LandingContent::personName());
        $twig->addGlobal('alsoKnownAs', LandingContent::alsoKnownAs());
        $twig->addGlobal('navPrefix', $navPrefix);
        $twig->addGlobal('navAnchors', $navItems);
        $twig->addGlobal('siteShell', $shell);
        $twig->addGlobal('fabHref', $fabHref);
        $twig->addGlobal('seo', $this->seoMetadataFactory->forRoute(
            $route,
            $request,
            $settings,
            $blocksBySlug,
        ));
    }
}
