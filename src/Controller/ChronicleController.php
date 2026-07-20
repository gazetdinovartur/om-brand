<?php

namespace App\Controller;

use App\Repository\ChronicleEntryRepository;
use App\Repository\ChronicleEraRepository;
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
    #[Route('/chronicle', name: 'web_chronicle', methods: ['GET'])]
    public function index(
        Request $request,
        ChronicleEntryRepository $entries,
        ChronicleEraRepository $eras,
        ChronicleTagRepository $tags,
        PublicSiteContext $siteContext,
        SeoMetadataFactory $seo,
    ): Response {
        return $this->render('web/chronicle/index.html.twig', [
            'entries' => $entries->findFeedOrdered(),
            'eras' => $eras->findAllOrdered(),
            'tags' => $tags->findAllOrdered(),
            'activeEra' => null,
            'activeTag' => null,
            'seo' => $seo->forChronicleIndex($request, $siteContext->getSettings(), $siteContext->getBlocksBySlugFiltered(['hero'])),
        ]);
    }

    #[Route('/chronicle/era/{slug}', name: 'web_chronicle_era', methods: ['GET'], requirements: ['slug' => '[a-z0-9\-]+'])]
    public function era(
        string $slug,
        Request $request,
        ChronicleEntryRepository $entries,
        ChronicleEraRepository $eras,
        ChronicleTagRepository $tags,
        PublicSiteContext $siteContext,
        SeoMetadataFactory $seo,
    ): Response {
        $era = $eras->findBySlug($slug);
        if (null === $era) {
            throw new NotFoundHttpException('Эпоха не найдена.');
        }

        return $this->render('web/chronicle/index.html.twig', [
            'entries' => $entries->findByEra($era),
            'eras' => $eras->findAllOrdered(),
            'tags' => $tags->findAllOrdered(),
            'activeEra' => $era,
            'activeTag' => null,
            'seo' => $seo->forChronicleEra($request, $siteContext->getSettings(), $siteContext->getBlocksBySlugFiltered(['hero']), $era),
        ]);
    }

    #[Route('/chronicle/tag/{slug}', name: 'web_chronicle_tag', methods: ['GET'], requirements: ['slug' => '[a-z0-9\-]+'])]
    public function tag(
        string $slug,
        Request $request,
        ChronicleEntryRepository $entries,
        ChronicleEraRepository $eras,
        ChronicleTagRepository $tags,
        PublicSiteContext $siteContext,
        SeoMetadataFactory $seo,
    ): Response {
        $tag = $tags->findBySlug($slug);
        if (null === $tag) {
            throw new NotFoundHttpException('Тег не найден.');
        }

        return $this->render('web/chronicle/index.html.twig', [
            'entries' => $entries->findByTag($tag),
            'eras' => $eras->findAllOrdered(),
            'tags' => $tags->findAllOrdered(),
            'activeEra' => null,
            'activeTag' => $tag,
            'seo' => $seo->forChronicleTag($request, $siteContext->getSettings(), $siteContext->getBlocksBySlugFiltered(['hero']), $tag),
        ]);
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
