<?php

namespace App\Controller;

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
    public function sitemap(): Response
    {
        $homeUrl = $this->generateUrl('web_home', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $privacyUrl = $this->generateUrl('web_privacy', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $lastmod = (new \DateTimeImmutable())->format('Y-m-d');

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>{$this->escapeXml($homeUrl)}</loc>
    <lastmod>{$lastmod}</lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc>{$this->escapeXml($privacyUrl)}</loc>
    <lastmod>{$lastmod}</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.4</priority>
  </url>
</urlset>
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
