<?php

namespace App\Controller;

use App\Entity\ChronicleEra;
use App\Entity\ChronicleSeries;
use App\Entity\ChronicleTag;
use App\Repository\ChronicleEntryRepository;
use App\Repository\ChronicleEraRepository;
use App\Repository\ChronicleSeriesRepository;
use App\Repository\ChronicleTagRepository;
use App\Seo\SeoMetadataFactory;
use App\Service\ChronicleMediaEmbedFactory;
use App\Service\PublicSiteContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ChronicleController extends AbstractController
{
    private const PAGE_SIZE = 24;

    #[Route('/chronicle', name: 'web_chronicle', methods: ['GET'])]
    public function index(
        Request $request,
        ChronicleEntryRepository $entries,
        ChronicleEraRepository $eras,
        ChronicleTagRepository $tags,
        ChronicleSeriesRepository $series,
        PublicSiteContext $siteContext,
        SeoMetadataFactory $seo,
    ): Response {
        return $this->renderFeed($request, $entries, $eras, $tags, $series, $siteContext, $seo);
    }

    #[Route('/chronicle/more', name: 'web_chronicle_more', methods: ['GET'], priority: 20)]
    public function more(
        Request $request,
        ChronicleEntryRepository $entries,
        ChronicleEraRepository $eras,
        ChronicleTagRepository $tags,
        ChronicleSeriesRepository $series,
    ): Response {
        [$filters, $offset] = $this->resolveFilters($request, $eras, $tags, $series);
        $page = $entries->findFiltered($filters, self::PAGE_SIZE, $offset);
        $total = $entries->countFiltered($filters);
        $nextOffset = $offset + \count($page);

        $html = $this->renderView('web/chronicle/_entries.html.twig', [
            'entries' => $page,
        ]);

        return $this->json([
            'html' => $html,
            'nextOffset' => $nextOffset,
            'hasMore' => $nextOffset < $total,
            'total' => $total,
        ]);
    }

    #[Route('/chronicle/era/{slug}', name: 'web_chronicle_era', methods: ['GET'], requirements: ['slug' => '[a-z0-9\-]+'])]
    public function era(
        string $slug,
        Request $request,
        ChronicleEntryRepository $entries,
        ChronicleEraRepository $eras,
        ChronicleTagRepository $tags,
        ChronicleSeriesRepository $series,
        PublicSiteContext $siteContext,
        SeoMetadataFactory $seo,
    ): Response {
        $era = $eras->findBySlug($slug);
        if (null === $era) {
            throw new NotFoundHttpException('Эпоха не найдена.');
        }

        $request->query->set('era', $era->getSlug());

        return $this->renderFeed($request, $entries, $eras, $tags, $series, $siteContext, $seo, $era);
    }

    #[Route('/chronicle/tag/{slug}', name: 'web_chronicle_tag', methods: ['GET'], requirements: ['slug' => '[a-z0-9\-]+'])]
    public function tag(
        string $slug,
        Request $request,
        ChronicleEntryRepository $entries,
        ChronicleEraRepository $eras,
        ChronicleTagRepository $tags,
        ChronicleSeriesRepository $series,
        PublicSiteContext $siteContext,
        SeoMetadataFactory $seo,
    ): Response {
        $tag = $tags->findBySlug($slug);
        if (null === $tag) {
            throw new NotFoundHttpException('Тег не найден.');
        }

        $request->query->set('tag', $tag->getSlug());

        return $this->renderFeed($request, $entries, $eras, $tags, $series, $siteContext, $seo, null, $tag);
    }

    #[Route('/chronicle/series/{slug}', name: 'web_chronicle_series', methods: ['GET'], requirements: ['slug' => '[a-z0-9\-]+'])]
    public function seriesRoute(
        string $slug,
        Request $request,
        ChronicleEntryRepository $entries,
        ChronicleEraRepository $eras,
        ChronicleTagRepository $tags,
        ChronicleSeriesRepository $series,
        PublicSiteContext $siteContext,
        SeoMetadataFactory $seo,
    ): Response {
        $item = $series->findOneBy(['slug' => $slug]);
        if (!$item instanceof ChronicleSeries) {
            throw new NotFoundHttpException('Канал не найден.');
        }

        $request->query->set('series', $item->getSlug());

        return $this->renderFeed($request, $entries, $eras, $tags, $series, $siteContext, $seo, null, null, $item);
    }

    #[Route('/chronicle/{slug}', name: 'web_chronicle_show', methods: ['GET'], requirements: ['slug' => '[a-z0-9\-]+'])]
    public function show(
        string $slug,
        Request $request,
        ChronicleEntryRepository $entries,
        PublicSiteContext $siteContext,
        SeoMetadataFactory $seo,
        ChronicleMediaEmbedFactory $media,
    ): Response {
        $entry = $entries->findPublishedBySlug($slug);
        if (null === $entry) {
            throw new NotFoundHttpException('Запись не найдена.');
        }

        return $this->renderEntry($entry, $request, $siteContext, $seo, $media, $entries, false);
    }

    #[Route('/chronicle/preview/{id}', name: 'web_chronicle_preview', methods: ['GET'], requirements: ['id' => '\d+'], priority: 10)]
    public function preview(
        int $id,
        Request $request,
        ChronicleEntryRepository $entries,
        PublicSiteContext $siteContext,
        SeoMetadataFactory $seo,
        ChronicleMediaEmbedFactory $media,
    ): Response {
        $token = (string) $request->query->get('token', '');
        $entry = $entries->find($id);
        if (!$entry instanceof \App\Entity\ChronicleEntry || !hash_equals($entry->getPreviewToken(), $token)) {
            throw new NotFoundHttpException('Предпросмотр недоступен.');
        }

        return $this->renderEntry($entry, $request, $siteContext, $seo, $media, $entries, true);
    }

    #[Route('/chronicle/feed.xml', name: 'web_chronicle_feed', methods: ['GET'], priority: 10)]
    public function feed(
        Request $request,
        ChronicleEntryRepository $entries,
        SeoMetadataFactory $seo,
        PublicSiteContext $siteContext,
    ): Response {
        $baseUrl = $seo->resolveBaseUrl($request);
        $settings = $siteContext->getSettings();

        $response = $this->render('web/chronicle/feed.xml.twig', [
            'entries' => $entries->findFeedOrdered(30),
            'baseUrl' => $baseUrl,
            'siteName' => $settings->getName(),
        ]);
        $response->headers->set('Content-Type', 'application/rss+xml; charset=UTF-8');

        return $response;
    }

    private function renderFeed(
        Request $request,
        ChronicleEntryRepository $entries,
        ChronicleEraRepository $eras,
        ChronicleTagRepository $tags,
        ChronicleSeriesRepository $series,
        PublicSiteContext $siteContext,
        SeoMetadataFactory $seo,
        ?ChronicleEra $activeEra = null,
        ?ChronicleTag $activeTag = null,
        ?ChronicleSeries $activeSeries = null,
    ): Response {
        [$filters, $offset] = $this->resolveFilters($request, $eras, $tags, $series, $activeEra, $activeTag, $activeSeries);
        $page = $entries->findFiltered($filters, self::PAGE_SIZE, $offset);
        $total = $entries->countFiltered($filters);
        $nextOffset = $offset + \count($page);

        $seoMeta = match (true) {
            $filters['era'] instanceof ChronicleEra => $seo->forChronicleEra($request, $siteContext->getSettings(), $siteContext->getBlocksBySlugFiltered(['hero']), $filters['era']),
            $filters['tag'] instanceof ChronicleTag => $seo->forChronicleTag($request, $siteContext->getSettings(), $siteContext->getBlocksBySlugFiltered(['hero']), $filters['tag']),
            default => $seo->forChronicleIndex($request, $siteContext->getSettings(), $siteContext->getBlocksBySlugFiltered(['hero'])),
        };

        return $this->render('web/chronicle/index.html.twig', [
            'entries' => $page,
            'eras' => $eras->findWithPublishedOrdered(),
            'tags' => $tags->findWithPublishedOrdered(),
            'seriesList' => $series->findWithPublishedOrdered(),
            'years' => $entries->findPublishedYears(),
            'activeEra' => $filters['era'] ?? null,
            'activeTag' => $filters['tag'] ?? null,
            'activeSeries' => $filters['series'] ?? null,
            'activeYear' => $filters['year'] ?? null,
            'activeFeatured' => $filters['featured'] ?? null,
            'filterQuery' => $this->filterQueryArray($filters),
            'total' => $total,
            'nextOffset' => $nextOffset,
            'hasMore' => $nextOffset < $total,
            'pageSize' => self::PAGE_SIZE,
            'seo' => $seoMeta,
        ]);
    }

    /**
     * @param array{era?: ?ChronicleEra, tag?: ?ChronicleTag, series?: ?ChronicleSeries, year?: ?int, featured?: ?bool} $filters
     *
     * @return array<string, string|int>
     */
    private function filterQueryArray(array $filters): array
    {
        $q = [];
        if (!empty($filters['series'])) {
            $q['series'] = $filters['series']->getSlug();
        }
        if (!empty($filters['era'])) {
            $q['era'] = $filters['era']->getSlug();
        }
        if (!empty($filters['tag'])) {
            $q['tag'] = $filters['tag']->getSlug();
        }
        if (!empty($filters['year'])) {
            $q['year'] = (int) $filters['year'];
        }
        if (!empty($filters['featured'])) {
            $q['featured'] = 1;
        }

        return $q;
    }

    /**
     * @return array{0: array{era?: ?ChronicleEra, tag?: ?ChronicleTag, series?: ?ChronicleSeries, year?: ?int, featured?: ?bool}, 1: int}
     */
    private function resolveFilters(
        Request $request,
        ChronicleEraRepository $eras,
        ChronicleTagRepository $tags,
        ChronicleSeriesRepository $series,
        ?ChronicleEra $forcedEra = null,
        ?ChronicleTag $forcedTag = null,
        ?ChronicleSeries $forcedSeries = null,
    ): array {
        $era = $forcedEra;
        if (null === $era) {
            $eraSlug = trim((string) $request->query->get('era', ''));
            $era = '' !== $eraSlug ? $eras->findBySlug($eraSlug) : null;
        }

        $tag = $forcedTag;
        if (null === $tag) {
            $tagSlug = trim((string) $request->query->get('tag', ''));
            $tag = '' !== $tagSlug ? $tags->findBySlug($tagSlug) : null;
        }

        $seriesItem = $forcedSeries;
        if (null === $seriesItem) {
            $seriesSlug = trim((string) $request->query->get('series', ''));
            $found = '' !== $seriesSlug ? $series->findOneBy(['slug' => $seriesSlug]) : null;
            $seriesItem = $found instanceof ChronicleSeries ? $found : null;
        }

        $yearRaw = $request->query->get('year');
        $year = is_numeric($yearRaw) ? (int) $yearRaw : null;
        if (null !== $year && ($year < 1990 || $year > 2100)) {
            $year = null;
        }

        $featured = null;
        if ('1' === (string) $request->query->get('featured', '')) {
            $featured = true;
        }

        $offset = max(0, (int) $request->query->get('offset', 0));

        return [[
            'era' => $era,
            'tag' => $tag,
            'series' => $seriesItem,
            'year' => $year,
            'featured' => $featured,
        ], $offset];
    }

    private function renderEntry(
        \App\Entity\ChronicleEntry $entry,
        Request $request,
        PublicSiteContext $siteContext,
        SeoMetadataFactory $seo,
        ChronicleMediaEmbedFactory $media,
        ChronicleEntryRepository $entries,
        bool $isPreview,
    ): Response {
        $settings = $siteContext->getSettings();
        $blocks = $entry->getBlocks()->toArray();
        $hasOmPlayer = $media->entryHasOmPlayer(...$blocks);

        $seoMeta = $seo->forChronicleEntry($request, $settings, $siteContext->getBlocksBySlugFiltered(['hero']), $entry);
        if ($isPreview) {
            $seoMeta = new \App\Seo\SeoMetadata(
                title: $seoMeta->title,
                description: $seoMeta->description,
                canonicalUrl: $seoMeta->canonicalUrl,
                robots: 'noindex, nofollow',
                ogType: $seoMeta->ogType,
                ogImageUrl: $seoMeta->ogImageUrl,
                jsonLd: null,
                keywords: null,
            );
        }

        return $this->render('web/chronicle/show.html.twig', [
            'entry' => $entry,
            'related' => $entries->findRelated($entry),
            'toc' => $entry->tableOfContents(),
            'hasOmPlayer' => $hasOmPlayer,
            'omPlayerScriptUrl' => $media->omPlayerScriptUrl(),
            'isPreview' => $isPreview,
            'seo' => $seoMeta,
        ]);
    }
}
