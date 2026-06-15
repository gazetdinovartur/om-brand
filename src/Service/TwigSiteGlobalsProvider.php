<?php

namespace App\Service;

use App\Content\LandingContent;
use App\Seo\SeoMetadataFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class TwigSiteGlobalsProvider
{
    public function __construct(
        private readonly PublicSiteContext $siteContext,
        private readonly SeoMetadataFactory $seoMetadataFactory,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(TURNSTILE_SITE_KEY)%')]
        private readonly string $turnstileSiteKey = '',
    ) {
    }

    public function apply(Environment $twig, Request $request, string $route = 'web_home'): void
    {
        $settings = $this->siteContext->getSettings();
        $blocksBySlug = $this->siteContext->getBlocksBySlugFiltered(['footer_legal', 'footer_excludes', 'hero']);

        $hero = $blocksBySlug['hero'] ?? null;
        $siteName = (\is_object($hero) && method_exists($hero, 'getTitle') && \is_string($hero->getTitle()) && '' !== $hero->getTitle())
            ? $hero->getTitle()
            : $settings->getName();

        $navPrefix = 'web_home' === $route ? '' : $this->urlGenerator->generate('web_home');

        $twig->addGlobal('settings', $settings);
        $twig->addGlobal('blocksBySlug', $blocksBySlug);
        $twig->addGlobal('siteName', $siteName);
        $twig->addGlobal('legalName', LandingContent::personName());
        $twig->addGlobal('alsoKnownAs', LandingContent::alsoKnownAs());
        $twig->addGlobal('turnstileSiteKey', $this->turnstileSiteKey);
        $twig->addGlobal('navPrefix', $navPrefix);
        $twig->addGlobal('navAnchors', LandingContent::navigationAnchors());
        $twig->addGlobal('seo', $this->seoMetadataFactory->forRoute(
            $route,
            $request,
            $settings,
            $blocksBySlug,
        ));
    }
}
