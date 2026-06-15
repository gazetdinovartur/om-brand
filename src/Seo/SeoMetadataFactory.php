<?php

namespace App\Seo;

use App\Content\LandingContent;
use App\Content\LegalContent;
use App\Entity\SiteSettings;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SeoMetadataFactory
{
    public function __construct(
        private readonly Packages $packages,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $siteUrl = '',
        private readonly string $projectDir = '',
    ) {
    }

    public function resolveBaseUrl(Request $request): string
    {
        if ('' !== $this->siteUrl) {
            return rtrim($this->siteUrl, '/');
        }

        return rtrim($request->getSchemeAndHttpHost(), '/');
    }

    /**
     * @param array<string, mixed> $blocksBySlug
     */
    public function forRoute(
        string $route,
        Request $request,
        SiteSettings $settings,
        array $blocksBySlug,
        ?string $paymentTitle = null,
    ): SeoMetadata {
        $baseUrl = $this->resolveBaseUrl($request);
        $personName = $this->personName($blocksBySlug, $settings);
        $description = $settings->getTagline() ?: LandingContent::metaDescription();
        $ogImageUrl = $this->resolveOgImageUrl($settings, $baseUrl);

        return match ($route) {
            'web_payment_show' => new SeoMetadata(
                title: sprintf('Оплата · %s', $paymentTitle ?? 'счёт'),
                description: 'Страница оплаты. Ссылка персональная и не предназначена для поисковых систем.',
                canonicalUrl: rtrim($baseUrl, '/').$request->getPathInfo(),
                robots: 'noindex, nofollow',
                ogType: 'website',
                ogImageUrl: $ogImageUrl,
            ),
            'web_privacy' => new SeoMetadata(
                title: LegalContent::privacyPolicyTitle().' · '.LandingContent::personName(),
                description: 'Политика конфиденциальности и обработки персональных данных.',
                canonicalUrl: rtrim($baseUrl, '/').$this->urlGenerator->generate('web_privacy'),
                robots: 'index, follow',
                ogType: 'website',
                ogImageUrl: $ogImageUrl,
            ),
            default => new SeoMetadata(
                title: LandingContent::metaTitle($personName),
                description: $description,
                canonicalUrl: rtrim($baseUrl, '/').$this->urlGenerator->generate('web_home'),
                robots: 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
                ogType: 'website',
                ogImageUrl: $ogImageUrl,
                jsonLd: 'web_home' === $route ? $this->homeJsonLd($personName, $settings, $baseUrl, $description, $ogImageUrl) : null,
            ),
        };
    }

    /**
     * @param array<string, mixed> $blocksBySlug
     */
    private function personName(array $blocksBySlug, SiteSettings $settings): string
    {
        $hero = $blocksBySlug['hero'] ?? null;

        if (\is_object($hero) && method_exists($hero, 'getTitle')) {
            $title = $hero->getTitle();
            if (\is_string($title) && '' !== $title) {
                return $title;
            }
        }

        return $settings->getName();
    }

    private function resolveOgImageUrl(SiteSettings $settings, string $baseUrl): ?string
    {
        $avatarPath = $settings->getAvatarPath();
        if (null !== $avatarPath && '' !== $avatarPath) {
            return $baseUrl.$this->packages->getUrl('uploads/avatars/'.$avatarPath);
        }

        if (is_file($this->projectDir.'/public/images/og-default.jpg')) {
            return $baseUrl.$this->packages->getUrl('images/og-default.jpg');
        }

        return $baseUrl.$this->packages->getUrl('images/og-default.svg');
    }

    /**
     * @return array<string, mixed>
     */
    private function homeJsonLd(
        string $personName,
        SiteSettings $settings,
        string $baseUrl,
        string $description,
        ?string $ogImageUrl,
    ): array {
        $homeUrl = rtrim($baseUrl, '/').$this->urlGenerator->generate('web_home');
        $jobTitle = $settings->getCity() ?: LandingContent::headerSubtitle();

        $person = [
            '@type' => 'Person',
            '@id' => $homeUrl.'#person',
            'name' => $personName,
            'jobTitle' => $jobTitle,
            'description' => $description,
            'url' => $homeUrl,
            'knowsAbout' => LandingContent::knowsAbout(),
        ];

        if (null !== $ogImageUrl) {
            $person['image'] = $ogImageUrl;
        }

        $sameAs = array_values(array_filter([
            $settings->getTelegramUrl(),
            $settings->getGithubUrl(),
        ]));
        if ([] !== $sameAs) {
            $person['sameAs'] = $sameAs;
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebSite',
                    '@id' => $homeUrl.'#website',
                    'url' => $homeUrl,
                    'name' => LandingContent::metaTitle($personName),
                    'description' => $description,
                    'inLanguage' => 'ru-RU',
                    'publisher' => ['@id' => $homeUrl.'#person'],
                ],
                [
                    '@type' => 'WebPage',
                    '@id' => $homeUrl.'#webpage',
                    'url' => $homeUrl,
                    'name' => LandingContent::metaTitle($personName),
                    'description' => $description,
                    'isPartOf' => ['@id' => $homeUrl.'#website'],
                    'about' => ['@id' => $homeUrl.'#person'],
                    'inLanguage' => 'ru-RU',
                ],
                $person,
                [
                    '@type' => 'ProfessionalService',
                    '@id' => $homeUrl.'#service',
                    'name' => LandingContent::serviceName($personName),
                    'description' => $description,
                    'url' => $homeUrl,
                    'provider' => ['@id' => $homeUrl.'#person'],
                    'areaServed' => LandingContent::areaServed(),
                    'serviceType' => LandingContent::serviceTypes(),
                ],
            ],
        ];
    }
}
