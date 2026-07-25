<?php

namespace App\Service;

use App\Entity\ChronicleBlock;
use App\Entity\ChronicleEntry;
use App\Enum\ChronicleBlockType;
use App\Enum\ChronicleStatus;
use App\Repository\ChronicleEntryRepository;
use App\Repository\ChronicleSeriesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Pulls new posts from VK wall into chronicle (anti-loop with site footer / vkPostId).
 */
final class ChronicleVkWallSync
{
    public function __construct(
        private readonly VkCredentials $credentials,
        private readonly VkApiClient $vk,
        private readonly ChronicleEntryRepository $entries,
        private readonly ChronicleSeriesRepository $series,
        private readonly ChronicleHashGenerator $hashGenerator,
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return array{created: int, skipped: int}
     */
    public function sync(int $count = 20): array
    {
        $stats = ['created' => 0, 'skipped' => 0];
        if (!$this->credentials->canCrosspost()) {
            return $stats;
        }

        $ownerId = $this->vk->resolveOwnerId();
        $vkSeries = $this->series->findOneBy(['slug' => 'vk-wall']);

        foreach ($this->vk->wallGet($ownerId, $count) as $post) {
            $postId = isset($post['id']) ? (int) $post['id'] : 0;
            if ($postId <= 0) {
                ++$stats['skipped'];
                continue;
            }

            if (null !== $this->entries->findOneByVkPostId($postId)) {
                ++$stats['skipped'];
                continue;
            }

            $sourceKey = 'vk:wall:'.$postId;
            if (null !== $this->entries->findOneByVkSourceKey($sourceKey)) {
                ++$stats['skipped'];
                continue;
            }

            $text = trim((string) ($post['text'] ?? ''));
            if ($this->looksLikeOurCrosspost($text)) {
                ++$stats['skipped'];
                continue;
            }

            $entry = new ChronicleEntry();
            $entry->setShortHash($this->hashGenerator->generateUnique());
            $entry->setSourceKey($sourceKey);
            $entry->setTitle($this->titleFromText($text, $postId));
            $entry->setSlug('vk-wall-'.$postId);
            $entry->setLede($this->ledeFromText($text));
            $entry->setStatus(ChronicleStatus::Published);
            $date = isset($post['date']) ? (int) $post['date'] : time();
            $entry->setPublishedAt((new \DateTimeImmutable())->setTimestamp($date));
            $entry->setVkPostId($postId);
            $entry->setVkPostedAt($entry->getPublishedAt());
            if (null !== $vkSeries) {
                $entry->setSeries($vkSeries);
            }

            $body = $this->stripSiteFooter($text);
            if ('' !== $body) {
                $block = new ChronicleBlock();
                $block->setType(ChronicleBlockType::Paragraph);
                $block->setBody($body);
                $block->setSortOrder(0);
                $entry->addBlock($block);
            }

            $sort = 10;
            foreach ($this->extractPhotoUrls($post) as $url) {
                $basename = $this->downloadPhoto($url);
                if (null === $basename) {
                    continue;
                }
                if (null === $entry->getCoverImagePath()) {
                    $entry->setCoverImagePath($basename);
                }
                $imageBlock = new ChronicleBlock();
                $imageBlock->setType(ChronicleBlockType::Image);
                $imageBlock->setImagePath($basename);
                $imageBlock->setSortOrder($sort);
                $entry->addBlock($imageBlock);
                $sort += 10;
            }

            $this->em->persist($entry);
            ++$stats['created'];
        }

        if ($stats['created'] > 0) {
            $this->em->flush();
        }

        return $stats;
    }

    public function looksLikeOurCrosspost(string $text): bool
    {
        if (preg_match('#Опубликовано на сайте:\s*https?://[^\s]+/p/[a-z0-9]{8}#u', $text)) {
            return true;
        }

        if (preg_match('#/p/([a-z0-9]{8})\b#', $text, $m)) {
            return null !== $this->entries->findOneByShortHashAny($m[1]);
        }

        return false;
    }

    private function titleFromText(string $text, int $postId): string
    {
        $clean = $this->stripSiteFooter($text);
        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? $clean);
        if ('' === $clean) {
            return 'Пост VK #'.$postId;
        }

        return mb_strlen($clean) > 80 ? rtrim(mb_substr($clean, 0, 77)).'…' : $clean;
    }

    private function ledeFromText(string $text): ?string
    {
        $clean = $this->stripSiteFooter($text);
        $clean = trim($clean);
        if ('' === $clean) {
            return null;
        }

        return mb_strlen($clean) > 400 ? rtrim(mb_substr($clean, 0, 397)).'…' : $clean;
    }

    private function stripSiteFooter(string $text): string
    {
        $text = preg_replace('#\n*\*\n+\n*Опубликовано на сайте:.*$#u', '', $text) ?? $text;
        $text = preg_replace('#\n*Опубликовано на сайте:.*$#u', '', $text) ?? $text;

        return trim($text);
    }

    /**
     * @param array<string, mixed> $post
     *
     * @return list<string>
     */
    private function extractPhotoUrls(array $post): array
    {
        $urls = [];
        $attachments = $post['attachments'] ?? [];
        if (!\is_array($attachments)) {
            return [];
        }

        foreach ($attachments as $attachment) {
            if (!\is_array($attachment) || ($attachment['type'] ?? '') !== 'photo') {
                continue;
            }
            $photo = $attachment['photo'] ?? null;
            if (!\is_array($photo)) {
                continue;
            }
            $sizes = $photo['sizes'] ?? [];
            if (!\is_array($sizes) || [] === $sizes) {
                continue;
            }
            usort($sizes, static function ($a, $b): int {
                $aw = (int) ($a['width'] ?? 0);
                $bw = (int) ($b['width'] ?? 0);

                return $bw <=> $aw;
            });
            $url = (string) ($sizes[0]['url'] ?? '');
            if ('' !== $url) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    private function downloadPhoto(string $url): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 60]);
            if (200 !== $response->getStatusCode()) {
                return null;
            }
            $bytes = $response->getContent();
            if ('' === $bytes) {
                return null;
            }

            $dir = $this->projectDir.'/public/uploads/chronicle/inline';
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                return null;
            }

            $basename = 'vk_'.bin2hex(random_bytes(8)).'.jpg';
            if (false === file_put_contents($dir.'/'.$basename, $bytes)) {
                return null;
            }

            return $basename;
        } catch (\Throwable) {
            return null;
        }
    }
}
