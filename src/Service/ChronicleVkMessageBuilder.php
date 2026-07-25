<?php

namespace App\Service;

use App\Entity\ChronicleEntry;
use App\Enum\ChronicleBlockType;
use App\Twig\UploadPathExtension;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds VK wall message + local image paths from a chronicle entry.
 */
final class ChronicleVkMessageBuilder
{
    private const MAX_MESSAGE_CHARS = 14000;

    public function __construct(
        private readonly UploadPathExtension $uploadPaths,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%env(default:app.site_url:APP_SITE_URL)%')]
        private readonly string $siteUrl = '',
    ) {
    }

    /**
     * @return array{message: string, imagePaths: list<string>, shortUrl: string, copyright: string}
     */
    public function build(ChronicleEntry $entry): array
    {
        $shortUrl = $this->absoluteShortUrl($entry);
        $parts = [];

        $title = trim($entry->getTitle());
        if ('' !== $title) {
            $parts[] = $title;
        }

        $lede = trim((string) $entry->getLede());
        if ('' !== $lede && !$this->sameProse($lede, $title)) {
            $parts[] = $lede;
        }

        foreach ($entry->getBlocks() as $block) {
            $chunk = match ($block->getType()) {
                ChronicleBlockType::Paragraph, ChronicleBlockType::Callout => trim((string) $block->getBody()),
                ChronicleBlockType::Heading => trim((string) $block->getBody()),
                ChronicleBlockType::Quote => $this->formatQuote(
                    trim((string) $block->getBody()),
                    trim((string) $block->getAuthor()),
                ),
                ChronicleBlockType::Divider => '',
                ChronicleBlockType::Image => trim((string) ($block->getCaption() ?? $block->getAlt() ?? '')),
                ChronicleBlockType::Gallery => '',
                ChronicleBlockType::Audio => $block->getOmTrackSlug()
                    ? 'Аудио: '.$block->getOmTrackSlug()
                    : '',
                ChronicleBlockType::Video => $block->getVideoUrl()
                    ? 'Видео: '.$block->getVideoUrl()
                    : trim((string) $block->getVideoTitle()),
            };

            if ('' !== $chunk) {
                $parts[] = $chunk;
            }
        }

        $body = trim(implode("\n\n", array_filter($parts, static fn (string $p): bool => '' !== trim($p))));
        $message = $body;

        if (mb_strlen($message) > self::MAX_MESSAGE_CHARS) {
            $message = rtrim(mb_substr($message, 0, self::MAX_MESSAGE_CHARS - 1)).'…';
        }

        return [
            'message' => $message,
            'imagePaths' => $this->collectImagePaths($entry),
            'shortUrl' => $shortUrl,
            // VK wall.post «Источник» (copyright) — системная пометка, не текст поста.
            'copyright' => $shortUrl,
        ];
    }

    public function absoluteShortUrl(ChronicleEntry $entry): string
    {
        $path = $this->urlGenerator->generate(
            'web_chronicle_short',
            ['hash' => $entry->getShortHash()],
            UrlGeneratorInterface::ABSOLUTE_PATH,
        );

        $base = rtrim($this->siteUrl, '/');
        if ('' === $base) {
            return $path;
        }

        return $base.$path;
    }

    /**
     * @return list<string> absolute filesystem paths
     */
    public function collectImagePaths(ChronicleEntry $entry): array
    {
        $paths = [];
        $coverRel = $this->uploadPaths->chronicleCover($entry->getCoverImagePath());
        if (null !== $coverRel) {
            $abs = $this->absolutePublicFile($coverRel);
            if (null !== $abs) {
                $paths[] = $abs;
            }
        }

        foreach ($entry->getBlocks() as $block) {
            if (ChronicleBlockType::Image === $block->getType()) {
                $rel = $this->uploadPaths->chronicleImage($block->getImagePath());
                if (null !== $rel) {
                    $abs = $this->absolutePublicFile($rel);
                    if (null !== $abs) {
                        $paths[] = $abs;
                    }
                }
            }
            if (ChronicleBlockType::Gallery === $block->getType()) {
                foreach ($block->getImages() as $image) {
                    $rel = $this->uploadPaths->chronicleImage($image->getImagePath(), ['chronicle/gallery', 'chronicle/inline', 'chronicle/covers']);
                    if (null !== $rel) {
                        $abs = $this->absolutePublicFile($rel);
                        if (null !== $abs) {
                            $paths[] = $abs;
                        }
                    }
                }
            }
        }

        // Unique while preserving order.
        $seen = [];
        $unique = [];
        foreach ($paths as $path) {
            if (isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;
            $unique[] = $path;
        }

        return $unique;
    }

    private function absolutePublicFile(string $publicRel): ?string
    {
        $path = $this->projectDir.'/public/'.ltrim(str_replace('\\', '/', $publicRel), '/');
        if (!is_file($path)) {
            return null;
        }

        return $path;
    }

    private function formatQuote(string $body, string $author): string
    {
        if ('' === $body) {
            return '';
        }
        $line = '«'.$body.'»';
        if ('' !== $author) {
            $line .= "\n— ".$author;
        }

        return $line;
    }

    private function sameProse(string $a, string $b): bool
    {
        $norm = static function (string $s): string {
            $s = mb_strtolower(trim($s));
            $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

            return $s;
        };

        return $norm($a) === $norm($b);
    }
}
