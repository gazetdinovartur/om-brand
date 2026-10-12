<?php

declare(strict_types=1);

namespace App\Library;

final class EpubBookParser
{
    public function parse(
        LibrarySource $source,
        ?string $titleOverride = null,
        ?string $authorOverride = null,
    ): ParsedLibraryBook {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Для импорта EPUB требуется расширение PHP zip.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'epub-');
        if (false === $tmp) {
            throw new \RuntimeException('Не удалось создать временный файл для EPUB.');
        }
        $tmpZip = $tmp.'.epub';
        @unlink($tmp);
        file_put_contents($tmpZip, $source->text);

        $zip = new \ZipArchive();
        $opened = $zip->open($tmpZip);
        if (true !== $opened) {
            @unlink($tmpZip);
            throw new \RuntimeException('Не удалось открыть EPUB.');
        }

        try {
            $container = $zip->getFromName('META-INF/container.xml');
            if (false === $container) {
                throw new \RuntimeException('В EPUB нет META-INF/container.xml.');
            }

            $opfPath = $this->resolveOpfPath($container);
            $opfXml = $zip->getFromName($opfPath);
            if (false === $opfXml) {
                throw new \RuntimeException('Не найден OPF внутри EPUB.');
            }

            $opfDir = trim(str_replace('\\', '/', dirname($opfPath)), '.');
            if ('.' === $opfDir) {
                $opfDir = '';
            }

            [$detectedTitle, $detectedAuthor, $spineHrefs] = $this->parseOpf($opfXml);
            $rawChapters = [];
            foreach ($spineHrefs as $index => $href) {
                $fullPath = '' === $opfDir ? $href : $opfDir.'/'.$href;
                $fullPath = $this->normalizeZipPath($fullPath);
                $html = $zip->getFromName($fullPath);
                if (false === $html) {
                    // Some epubs URL-encode paths
                    $html = $zip->getFromName(rawurldecode($fullPath));
                }
                if (false === $html) {
                    continue;
                }
                if (!mb_check_encoding($html, 'UTF-8')) {
                    $converted = @iconv('Windows-1251', 'UTF-8', $html);
                    $html = false === $converted ? $html : $converted;
                }

                $chapterTitle = $this->htmlTitle($html) ?? ('Глава '.($index + 1));
                $paragraphs = BookParseSupport::stripHtmlToParagraphs($html);
                if ([] === $paragraphs) {
                    continue;
                }
                $rawChapters[] = [
                    'title' => $chapterTitle,
                    'slug' => BookParseSupport::slugify($chapterTitle, 'chapter-'.($index + 1)),
                    'paragraphs' => $paragraphs,
                ];
            }
        } finally {
            $zip->close();
            @unlink($tmpZip);
        }

        if ([] === $rawChapters) {
            $rawChapters[] = [
                'title' => 'Книга',
                'slug' => 'book',
                'paragraphs' => ['Не удалось извлечь текст из EPUB.'],
            ];
        }

        $footnotes = [];
        $chapters = BookParseSupport::chaptersFromRaw($rawChapters, $footnotes);
        [$title, $author] = BookParseSupport::resolveTitleAuthor(
            $titleOverride,
            $authorOverride,
            $detectedTitle,
            $detectedAuthor,
            $source->sourceName,
        );

        return BookParseSupport::finishBook($title, $author, $source->checksum, $chapters, $footnotes);
    }

    private function resolveOpfPath(string $containerXml): string
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadXML($containerXml, \LIBXML_NONET | \LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('c', 'urn:oasis:names:tc:opendocument:xmlns:container');
        $nodes = $xpath->query('//c:rootfile[@full-path]/i | //*[local-name()="rootfile"][@full-path]');
        $path = $nodes instanceof \DOMNodeList && $nodes->length > 0
            ? (string) $nodes->item(0)?->attributes?->getNamedItem('full-path')?->nodeValue
            : '';
        $path = $this->normalizeZipPath($path);
        if ('' === $path) {
            throw new \RuntimeException('В container.xml не указан OPF.');
        }

        return $path;
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: list<string>}
     */
    private function parseOpf(string $opfXml): array
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadXML($opfXml, \LIBXML_NONET | \LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('opf', 'http://www.idpf.org/2007/opf');
        $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');

        $title = $this->xpathText($xpath, '//dc:title')
            ?? $this->xpathText($xpath, '//*[local-name()="title"]');
        $author = $this->xpathText($xpath, '//dc:creator')
            ?? $this->xpathText($xpath, '//*[local-name()="creator"]');

        $manifest = [];
        $items = $xpath->query('//opf:manifest/opf:item | //*[local-name()="manifest"]/*[local-name()="item"]');
        if ($items instanceof \DOMNodeList) {
            foreach ($items as $item) {
                if (!$item instanceof \DOMElement) {
                    continue;
                }
                $id = $item->getAttribute('id');
                $href = $item->getAttribute('href');
                if ('' !== $id && '' !== $href) {
                    $manifest[$id] = rawurldecode($href);
                }
            }
        }

        $spine = [];
        $itemrefs = $xpath->query('//opf:spine/opf:itemref | //*[local-name()="spine"]/*[local-name()="itemref"]');
        if ($itemrefs instanceof \DOMNodeList) {
            foreach ($itemrefs as $itemref) {
                if (!$itemref instanceof \DOMElement) {
                    continue;
                }
                $idref = $itemref->getAttribute('idref');
                if (isset($manifest[$idref])) {
                    $spine[] = $manifest[$idref];
                }
            }
        }

        return [$title, $author, $spine];
    }

    private function xpathText(\DOMXPath $xpath, string $query): ?string
    {
        $nodes = $xpath->query($query);
        if (!$nodes instanceof \DOMNodeList || 0 === $nodes->length) {
            return null;
        }
        $text = BookParseSupport::normalizeParagraph($nodes->item(0)?->textContent ?? '');

        return '' === $text ? null : $text;
    }

    private function htmlTitle(string $html): ?string
    {
        if (preg_match('/<h1\b[^>]*>(.*?)<\/h1>/is', $html, $matches)) {
            $text = BookParseSupport::normalizeParagraph(html_entity_decode(strip_tags($matches[1]), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));

            return '' === $text ? null : $text;
        }
        if (preg_match('/<title\b[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            $text = BookParseSupport::normalizeParagraph(html_entity_decode(strip_tags($matches[1]), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));

            return '' === $text ? null : $text;
        }

        return null;
    }

    private function normalizeZipPath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ('' === $part || '.' === $part) {
                continue;
            }
            if ('..' === $part) {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }
}
