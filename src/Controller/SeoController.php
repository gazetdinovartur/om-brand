<?php

namespace App\Controller;

use App\Repository\CaseStudyRepository;
use App\Repository\ChronicleEntryRepository;
use App\Seo\SeoMetadataFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SeoController extends AbstractController
{
    #[Route('/robots.txt', name: 'web_robots', methods: ['GET'])]
    public function robots(Request $request, SeoMetadataFactory $seoMetadataFactory): Response
    {
        $baseUrl = $seoMetadataFactory->resolveBaseUrl($request);
        $sitemapUrl = $this->generateUrl('web_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $content = <<<TXT
User-agent: *
Allow: /

Disallow: /admin/
Disallow: /oplata/

Sitemap: {$sitemapUrl}

TXT;

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    #[Route('/sitemap.xml', name: 'web_sitemap', methods: ['GET'])]
    public function sitemap(
        CaseStudyRepository $caseStudyRepository,
        ChronicleEntryRepository $chronicleEntryRepository,
    ): Response {
        $homeUrl = $this->generateUrl('web_home', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $privacyUrl = $this->generateUrl('web_privacy', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $casesUrl = $this->generateUrl('web_cases', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $chronicleUrl = $this->generateUrl('web_chronicle', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $lastmod = (new \DateTimeImmutable())->format('Y-m-d');

        $urls = [
            [$homeUrl, $lastmod, 'weekly', '1.0'],
            [$casesUrl, $lastmod, 'weekly', '0.8'],
            [$chronicleUrl, $lastmod, 'weekly', '0.8'],
            [$privacyUrl, $lastmod, 'monthly', '0.4'],
        ];

        foreach ($caseStudyRepository->findPublishedOrdered() as $case) {
            if (!$case->isDetailPublic()) {
                continue;
            }
            $urls[] = [
                $this->generateUrl('web_case_show', ['slug' => $case->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                $case->getCreatedAt()->format('Y-m-d'),
                'monthly',
                '0.7',
            ];
        }

        foreach ($chronicleEntryRepository->findForSitemap() as $entry) {
            $urls[] = [
                $this->generateUrl('web_chronicle_show', ['slug' => $entry->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                ($entry->getPublishedAt() ?? $entry->getCreatedAt())->format('Y-m-d'),
                'monthly',
                '0.7',
            ];
        }

        $body = '';
        foreach ($urls as [$loc, $mod, $freq, $priority]) {
            $body .= <<<XML
  <url>
    <loc>{$this->escapeXml($loc)}</loc>
    <lastmod>{$mod}</lastmod>
    <changefreq>{$freq}</changefreq>
    <priority>{$priority}</priority>
  </url>

XML;
        }

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
{$body}</urlset>
XML;

        return new Response($xml, Response::HTTP_OK, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
