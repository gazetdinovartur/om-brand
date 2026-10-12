<?php

declare(strict_types=1);

namespace App\Library;

final class Fb2BookParser
{
    public function parse(
        LibrarySource $source,
        ?string $titleOverride = null,
        ?string $authorOverride = null,
    ): ParsedLibraryBook {
        $xml = $this->normalizeToUtf8Xml($source->text);

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($xml, \LIBXML_NONET | \LIBXML_COMPACT);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            // Last resort: plain text of the XML payload (should be rare after encoding fix).
            return (new PlainTextBookParser())->parse(
                new LibrarySource($xml, $source->checksum, $source->sourceName, LibrarySource::FORMAT_TXT),
                $titleOverride,
                $authorOverride,
            );
        }

        unset($errors);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('fb', 'http://www.gribuser.ru/xml/fictionbook/2.0');

        $detectedTitle = $this->firstText($xpath, '//fb:description/fb:title-info/fb:book-title')
            ?? $this->firstText($xpath, '//*[local-name()="book-title"]');
        $firstName = $this->firstText($xpath, '//fb:description/fb:title-info/fb:author/fb:first-name')
            ?? $this->firstText($xpath, '//*[local-name()="author"]/*[local-name()="first-name"]');
        $lastName = $this->firstText($xpath, '//fb:description/fb:title-info/fb:author/fb:last-name')
            ?? $this->firstText($xpath, '//*[local-name()="author"]/*[local-name()="last-name"]');
        $middleName = $this->firstText($xpath, '//fb:description/fb:title-info/fb:author/fb:middle-name')
            ?? $this->firstText($xpath, '//*[local-name()="author"]/*[local-name()="middle-name"]');
        $nickname = $this->firstText($xpath, '//fb:description/fb:title-info/fb:author/fb:nickname')
            ?? $this->firstText($xpath, '//*[local-name()="author"]/*[local-name()="nickname"]');
        $detectedAuthor = trim(implode(' ', array_filter([$firstName, $middleName, $lastName], static fn (?string $v): bool => null !== $v && '' !== $v)));
        if ('' === $detectedAuthor && null !== $nickname) {
            $detectedAuthor = $nickname;
        }

        $bodyNodes = $xpath->query('//fb:body[not(@name)]');
        if (!$bodyNodes instanceof \DOMNodeList || 0 === $bodyNodes->length) {
            $bodyNodes = $xpath->query('//*[local-name()="body" and not(@name)]');
        }
        $rawChapters = [];
        if ($bodyNodes instanceof \DOMNodeList) {
            foreach ($bodyNodes as $body) {
                if (!$body instanceof \DOMElement) {
                    continue;
                }
                $this->collectSections($body, $rawChapters);
            }
        }

        if ([] === $rawChapters) {
            $paragraphs = $this->paragraphsFromElement($dom->documentElement);
            $rawChapters[] = [
                'title' => 'Книга',
                'slug' => 'book',
                'paragraphs' => $paragraphs,
            ];
        }

        $footnotes = [];
        $chapters = BookParseSupport::chaptersFromRaw($rawChapters, $footnotes);
        [$title, $author] = BookParseSupport::resolveTitleAuthor(
            $titleOverride,
            $authorOverride,
            $detectedTitle,
            $detectedAuthor !== '' ? $detectedAuthor : null,
            $source->sourceName,
        );

        return BookParseSupport::finishBook($title, $author, $source->checksum, $chapters, $footnotes);
    }

    /**
     * Convert FB2 payload to UTF-8 XML with a matching encoding declaration.
     * Royallib and many Telegram dumps ship windows-1251 with that declaration;
     * converting bytes without rewriting the declaration breaks libxml in Docker.
     */
    private function normalizeToUtf8Xml(string $bytes): string
    {
        $encoding = 'UTF-8';
        if (preg_match('/encoding\s*=\s*["\']\s*([^"\']+)\s*["\']/i', substr($bytes, 0, 256), $matches)) {
            $encoding = strtoupper(trim($matches[1]));
        }

        if (!\in_array($encoding, ['UTF-8', 'UTF8'], true)) {
            $converted = @iconv($encoding, 'UTF-8//IGNORE', $bytes);
            if (false === $converted || '' === $converted) {
                $converted = @iconv('Windows-1251', 'UTF-8//IGNORE', $bytes);
            }
            if (false !== $converted && '' !== $converted) {
                $bytes = $converted;
            }
        } elseif (1 !== preg_match('//u', $bytes)) {
            $converted = @iconv('Windows-1251', 'UTF-8//IGNORE', $bytes);
            if (false !== $converted && '' !== $converted) {
                $bytes = $converted;
            }
        }

        if (preg_match('/encoding\s*=\s*["\'][^"\']*["\']/i', $bytes)) {
            $bytes = preg_replace('/encoding\s*=\s*["\'][^"\']*["\']/i', 'encoding="UTF-8"', $bytes, 1) ?? $bytes;
        } elseif (str_starts_with(ltrim($bytes), '<?xml')) {
            $bytes = preg_replace('/<\?xml\b[^?]*\?>/i', '<?xml version="1.0" encoding="UTF-8"?>', $bytes, 1) ?? $bytes;
        }

        return str_replace(["\r\n", "\r"], "\n", $bytes);
    }

    /**
     * @param list<array{title: string, slug: string, paragraphs: list<string>}> $rawChapters
     */
    private function collectSections(\DOMElement $parent, array &$rawChapters): void
    {
        $hasSectionChild = false;
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && 'section' === $child->localName) {
                $hasSectionChild = true;
                break;
            }
        }

        if ($hasSectionChild) {
            foreach ($parent->childNodes as $child) {
                if ($child instanceof \DOMElement && 'section' === $child->localName) {
                    $this->collectSections($child, $rawChapters);
                }
            }

            return;
        }

        $title = $this->sectionTitle($parent) ?? ('Глава '.(\count($rawChapters) + 1));
        $paragraphs = $this->paragraphsFromElement($parent, skipTitle: true);
        if ([] === $paragraphs) {
            return;
        }

        $rawChapters[] = [
            'title' => $title,
            'slug' => BookParseSupport::slugify($title, 'chapter-'.(\count($rawChapters) + 1)),
            'paragraphs' => $paragraphs,
        ];
    }

    private function sectionTitle(\DOMElement $section): ?string
    {
        foreach ($section->childNodes as $child) {
            if ($child instanceof \DOMElement && 'title' === $child->localName) {
                $text = BookParseSupport::normalizeParagraph($child->textContent ?? '');

                return '' === $text ? null : $text;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function paragraphsFromElement(?\DOMElement $element, bool $skipTitle = false): array
    {
        if (!$element instanceof \DOMElement) {
            return [];
        }

        $paragraphs = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($skipTitle && 'title' === $child->localName) {
                continue;
            }
            if ('p' === $child->localName || 'v' === $child->localName || 'subtitle' === $child->localName) {
                $text = BookParseSupport::normalizeParagraph($child->textContent ?? '');
                if ('' !== $text) {
                    $paragraphs[] = $text;
                }
                continue;
            }
            if (\in_array($child->localName, ['section', 'epigraph', 'cite', 'poem', 'stanza'], true)) {
                foreach ($this->paragraphsFromElement($child) as $nested) {
                    $paragraphs[] = $nested;
                }
            }
        }

        return $paragraphs;
    }

    private function firstText(\DOMXPath $xpath, string $query): ?string
    {
        $nodes = $xpath->query($query);
        if (!$nodes instanceof \DOMNodeList || 0 === $nodes->length) {
            return null;
        }
        $text = BookParseSupport::normalizeParagraph($nodes->item(0)?->textContent ?? '');

        return '' === $text ? null : $text;
    }
}
