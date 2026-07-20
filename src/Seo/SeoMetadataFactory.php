<?php

namespace App\Seo;

use App\Content\LandingContent;
use App\Content\LegalContent;
use App\Entity\CaseStudy;
use App\Entity\ChronicleEntry;
use App\Entity\ChronicleEra;
use App\Entity\ChronicleTag;
use App\Entity\SiteSettings;
use App\Twig\UploadPathExtension;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SeoMetadataFactory
{
    public function __construct(
        private readonly Packages $packages,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UploadPathExtension $uploadPathExtension,
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
            'web_cases' => $this->forCasesIndex($request, $settings, $blocksBySlug),
            'web_chronicle', 'web_chronicle_era', 'web_chronicle_tag' => $this->forChronicleIndex($request, $settings, $blocksBySlug),
            default => new SeoMetadata(
                title: LandingContent::metaTitle($personName),
                description: $description,
                canonicalUrl: rtrim($baseUrl, '/').$this->urlGenerator->generate('web_home'),
                robots: 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
                ogType: 'website',
                ogImageUrl: $ogImageUrl,
                jsonLd: 'web_home' === $route ? $this->homeJsonLd($personName, $settings, $baseUrl, $description, $ogImageUrl) : null,
                keywords: implode(', ', LandingContent::metaKeywords()),
            ),
        };
    }

    /**
     * @param array<string, mixed> $blocksBySlug
     */
    public function forCasesIndex(Request $request, SiteSettings $settings, array $blocksBySlug): SeoMetadata
    {
        $baseUrl = $this->resolveBaseUrl($request);
        $personName = $this->personName($blocksBySlug, $settings);
        $title = sprintf('Кейсы · %s', $personName);
        $description = 'Истории проектов: подход, опыт и результаты.';

        return new SeoMetadata(
            title: $title,
            description: $description,
            canonicalUrl: rtrim($baseUrl, '/').$this->urlGenerator->generate('web_cases'),
            robots: 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
            ogType: 'website',
            ogImageUrl: $this->resolveOgImageUrl($settings, $baseUrl),
            keywords: implode(', ', array_merge(['кейсы', 'портфолио'], LandingContent::metaKeywords())),
        );
    }

    /**
     * @param array<string, mixed> $blocksBySlug
     */
    public function forCaseStudy(
        Request $request,
        SiteSettings $settings,
        array $blocksBySlug,
        CaseStudy $case,
    ): SeoMetadata {
        $baseUrl = $this->resolveBaseUrl($request);
        $personName = $this->personName($blocksBySlug, $settings);
        $ogImageUrl = $this->resolveCaseOgImageUrl($case, $settings, $baseUrl);

        return new SeoMetadata(
            title: $case->resolveSeoTitle($personName),
            description: $case->resolveSeoDescription(),
            canonicalUrl: rtrim($baseUrl, '/').$this->urlGenerator->generate('web_case_show', ['slug' => $case->getSlug()]),
            robots: 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
            ogType: 'article',
            ogImageUrl: $ogImageUrl,
            jsonLd: $this->caseJsonLd($case, $personName, $baseUrl, $ogImageUrl),
        );
    }

    private function resolveCaseOgImageUrl(CaseStudy $case, SiteSettings $settings, string $baseUrl): ?string
    {
        foreach ([$case->getOgImagePath(), $case->getCoverImagePath()] as $path) {
            $resolved = $this->uploadPathExtension->resolve($path, 'cases');
            if (null !== $resolved) {
                return $baseUrl.$this->packages->getUrl($resolved);
            }
        }

        return $this->resolveOgImageUrl($settings, $baseUrl);
    }

    /**
     * @return array<string, mixed>
     */
    private function caseJsonLd(
        CaseStudy $case,
        string $personName,
        string $baseUrl,
        ?string $ogImageUrl,
    ): array {
        $url = rtrim($baseUrl, '/').$this->urlGenerator->generate('web_case_show', ['slug' => $case->getSlug()]);
        $homeUrl = rtrim($baseUrl, '/').$this->urlGenerator->generate('web_home');

        $article = [
            '@type' => 'Article',
            '@id' => $url.'#article',
            'headline' => $case->getTitle(),
            'description' => $case->resolveSeoDescription(),
            'datePublished' => $case->getCreatedAt()->format('c'),
            'author' => [
                '@type' => 'Person',
                'name' => $personName,
                'url' => $homeUrl,
            ],
            'mainEntityOfPage' => $url,
            'inLanguage' => 'ru-RU',
        ];

        if (null !== $ogImageUrl) {
            $article['image'] = $ogImageUrl;
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                $article,
                [
                    '@type' => 'BreadcrumbList',
                    '@id' => $url.'#breadcrumb',
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'name' => 'Главная',
                            'item' => $homeUrl,
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'name' => 'Кейсы',
                            'item' => rtrim($baseUrl, '/').$this->urlGenerator->generate('web_cases'),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 3,
                            'name' => $case->getTitle(),
                            'item' => $url,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $blocksBySlug
     */
    public function forChronicleIndex(Request $request, SiteSettings $settings, array $blocksBySlug): SeoMetadata
    {
        $baseUrl = $this->resolveBaseUrl($request);
        $personName = $this->personName($blocksBySlug, $settings);

        return new SeoMetadata(
            title: sprintf('Хроника · %s', $personName),
            description: 'Тексты и фото — по эпохам жизни, без шума.',
            canonicalUrl: rtrim($baseUrl, '/').$this->urlGenerator->generate('web_chronicle'),
            robots: 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
            ogType: 'website',
            ogImageUrl: $this->resolveOgImageUrl($settings, $baseUrl),
        );
    }

    /**
     * @param array<string, mixed> $blocksBySlug
     */
    public function forChronicleEra(Request $request, SiteSettings $settings, array $blocksBySlug, ChronicleEra $era): SeoMetadata
    {
        $baseUrl = $this->resolveBaseUrl($request);
        $personName = $this->personName($blocksBySlug, $settings);

        return new SeoMetadata(
            title: sprintf('%s · Хроника · %s', $era->getTitle(), $personName),
            description: $era->getDescription() ?: sprintf('Записи эпохи «%s».', $era->getTitle()),
            canonicalUrl: rtrim($baseUrl, '/').$this->urlGenerator->generate('web_chronicle_era', ['slug' => $era->getSlug()]),
            robots: 'index, follow',
            ogType: 'website',
            ogImageUrl: $this->resolveOgImageUrl($settings, $baseUrl),
        );
    }

    /**
     * @param array<string, mixed> $blocksBySlug
     */
    public function forChronicleTag(Request $request, SiteSettings $settings, array $blocksBySlug, ChronicleTag $tag): SeoMetadata
    {
        $baseUrl = $this->resolveBaseUrl($request);
        $personName = $this->personName($blocksBySlug, $settings);

        return new SeoMetadata(
            title: sprintf('#%s · Хроника · %s', $tag->getName(), $personName),
            description: sprintf('Записи с тегом «%s».', $tag->getName()),
            canonicalUrl: rtrim($baseUrl, '/').$this->urlGenerator->generate('web_chronicle_tag', ['slug' => $tag->getSlug()]),
            robots: 'index, follow',
            ogType: 'website',
            ogImageUrl: $this->resolveOgImageUrl($settings, $baseUrl),
        );
    }

    /**
     * @param array<string, mixed> $blocksBySlug
     */
    public function forChronicleEntry(
        Request $request,
        SiteSettings $settings,
        array $blocksBySlug,
        ChronicleEntry $entry,
    ): SeoMetadata {
        $baseUrl = $this->resolveBaseUrl($request);
        $personName = $this->personName($blocksBySlug, $settings);
        $ogImageUrl = $this->resolveChronicleOgImageUrl($entry, $settings, $baseUrl);

        return new SeoMetadata(
            title: $entry->resolveSeoTitle($personName),
            description: $entry->resolveSeoDescription(),
            canonicalUrl: rtrim($baseUrl, '/').$this->urlGenerator->generate('web_chronicle_show', ['slug' => $entry->getSlug()]),
            robots: $entry->isUnlisted() ? 'noindex, follow' : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
            ogType: 'article',
            ogImageUrl: $ogImageUrl,
            jsonLd: $this->chronicleJsonLd($entry, $personName, $baseUrl, $ogImageUrl),
        );
    }

    private function resolveChronicleOgImageUrl(ChronicleEntry $entry, SiteSettings $settings, string $baseUrl): ?string
    {
        foreach ([$entry->getOgImagePath(), $entry->getCoverImagePath()] as $path) {
            $resolved = $this->uploadPathExtension->resolve($path, 'chronicle/covers');
            if (null !== $resolved) {
                return $baseUrl.$this->packages->getUrl($resolved);
            }
        }

        return $this->resolveOgImageUrl($settings, $baseUrl);
    }

    /**
     * @return array<string, mixed>
     */
    private function chronicleJsonLd(
        ChronicleEntry $entry,
        string $personName,
        string $baseUrl,
        ?string $ogImageUrl,
    ): array {
        $url = rtrim($baseUrl, '/').$this->urlGenerator->generate('web_chronicle_show', ['slug' => $entry->getSlug()]);
        $homeUrl = rtrim($baseUrl, '/').$this->urlGenerator->generate('web_home');
        $chronicleUrl = rtrim($baseUrl, '/').$this->urlGenerator->generate('web_chronicle');

        $article = [
            '@type' => 'Article',
            '@id' => $url.'#article',
            'headline' => $entry->getTitle(),
            'description' => $entry->resolveSeoDescription(),
            'datePublished' => ($entry->getPublishedAt() ?? $entry->getCreatedAt())->format('c'),
            'dateModified' => $entry->getUpdatedAt()->format('c'),
            'author' => [
                '@type' => 'Person',
                'name' => $personName,
                'url' => $homeUrl,
            ],
            'mainEntityOfPage' => $url,
            'inLanguage' => 'ru-RU',
        ];

        if (null !== $ogImageUrl) {
            $article['image'] = $ogImageUrl;
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                $article,
                [
                    '@type' => 'BreadcrumbList',
                    '@id' => $url.'#breadcrumb',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => $homeUrl],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Хроника', 'item' => $chronicleUrl],
                        ['@type' => 'ListItem', 'position' => 3, 'name' => $entry->getTitle(), 'item' => $url],
                    ],
                ],
            ],
        ];
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
        $jobTitle = LandingContent::headerSubtitle();

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

        $contactPoint = [];
        if (null !== $settings->getEmail() && '' !== $settings->getEmail()) {
            $contactPoint[] = [
                '@type' => 'ContactPoint',
                'contactType' => 'customer support',
                'email' => $settings->getEmail(),
                'availableLanguage' => ['Russian'],
            ];
        }
        if (null !== $settings->getTelegramUrl() && '' !== $settings->getTelegramUrl()) {
            $contactPoint[] = [
                '@type' => 'ContactPoint',
                'contactType' => 'sales',
                'url' => $settings->getTelegramUrl(),
                'availableLanguage' => ['Russian'],
            ];
        }

        $service = [
            '@type' => 'ProfessionalService',
            '@id' => $homeUrl.'#service',
            'name' => LandingContent::serviceName($personName),
            'description' => $description,
            'url' => $homeUrl,
            'provider' => ['@id' => $homeUrl.'#person'],
            'areaServed' => LandingContent::areaServed(),
            'serviceType' => LandingContent::serviceTypes(),
        ];
        if ([] !== $contactPoint) {
            $service['contactPoint'] = $contactPoint;
        }

        $faqPage = [
            '@type' => 'FAQPage',
            '@id' => $homeUrl.'#faq',
            'mainEntity' => array_map(
                static fn (array $item): array => [
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $item['answer'],
                    ],
                ],
                LandingContent::faqSchemaItems(),
            ),
        ];

        $webPage = [
            '@type' => 'WebPage',
            '@id' => $homeUrl.'#webpage',
            'url' => $homeUrl,
            'name' => LandingContent::metaTitle($personName),
            'description' => $description,
            'isPartOf' => ['@id' => $homeUrl.'#website'],
            'about' => ['@id' => $homeUrl.'#person'],
            'inLanguage' => 'ru-RU',
            'potentialAction' => [
                '@type' => 'CommunicateAction',
                'name' => 'Оставить заявку',
                'target' => $homeUrl.'#contact',
            ],
        ];
        if (null !== $ogImageUrl) {
            $webPage['primaryImageOfPage'] = $ogImageUrl;
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
                $webPage,
                $person,
                $service,
                $faqPage,
            ],
        ];
    }
}
