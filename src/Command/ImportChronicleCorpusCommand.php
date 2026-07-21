<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ChronicleBlock;
use App\Entity\ChronicleEntry;
use App\Entity\ChronicleTag;
use App\Enum\ChronicleBlockType;
use App\Enum\ChronicleStatus;
use App\Repository\ChronicleEntryRepository;
use App\Repository\ChronicleEraRepository;
use App\Repository\ChronicleSeriesRepository;
use App\Repository\ChronicleTagRepository;
use App\Service\ChronicleHashGenerator;
use App\Service\ChronicleMarkdownRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:chronicle:import',
    description: 'Импортирует corpus/chronicle_entries.jsonl в Chronicle (идемпотентно по source_key)',
)]
final class ImportChronicleCorpusCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ChronicleEntryRepository $entries,
        private readonly ChronicleEraRepository $eras,
        private readonly ChronicleSeriesRepository $series,
        private readonly ChronicleTagRepository $tags,
        private readonly ChronicleHashGenerator $hashGenerator,
        private readonly ChronicleMarkdownRenderer $markdown,
        private readonly SluggerInterface $slugger,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('channel', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Только эти каналы (da-i-da, om, research, culture, vk, instagram)')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Максимум записей', '0')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Только посчитать')
            ->addOption('skip-media', null, InputOption::VALUE_NONE, 'Не копировать картинки')
            ->addOption('sync-tags', null, InputOption::VALUE_NONE, 'Перезаписать теги из corpus (по умолчанию сохраняются заданные в админке)')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Путь к jsonl', 'corpus/chronicle_entries.jsonl');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $this->projectDir.'/'.ltrim((string) $input->getOption('file'), '/');
        if (!is_file($file)) {
            $io->error('Нет файла '.$file.' — сначала: python3 scripts/corpus_build.py');

            return Command::FAILURE;
        }

        /** @var list<string> $channels */
        $channels = $input->getOption('channel');
        $channelFilter = [] === $channels ? null : array_fill_keys($channels, true);
        $limit = max(0, (int) $input->getOption('limit'));
        $dryRun = (bool) $input->getOption('dry-run');
        $skipMedia = (bool) $input->getOption('skip-media');
        $syncTags = (bool) $input->getOption('sync-tags');

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $n = 0;

        $handle = fopen($file, 'rb');
        if (false === $handle) {
            $io->error('Cannot open '.$file);

            return Command::FAILURE;
        }

        $tagCache = [];
        foreach ($this->tags->findAll() as $tag) {
            $tagCache[$tag->getSlug()] = $tag;
        }
        $eraCache = [];
        foreach ($this->eras->findAll() as $era) {
            $eraCache[$era->getSlug()] = $era;
        }
        $seriesCache = [];
        foreach ($this->series->findAll() as $series) {
            $seriesCache[$series->getSlug()] = $series;
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ('' === $line) {
                continue;
            }
            /** @var array<string, mixed> $row */
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $channel = (string) ($row['channel'] ?? '');
            if (null !== $channelFilter && !isset($channelFilter[$channel])) {
                continue;
            }

            ++$n;
            if ($limit > 0 && ($created + $updated) >= $limit) {
                break;
            }

            $sourceKey = (string) ($row['source_key'] ?? '');
            if ('' === $sourceKey) {
                ++$skipped;
                continue;
            }

            if ($dryRun) {
                ++$created;
                continue;
            }

            $entry = $this->entries->findOneBy(['sourceKey' => $sourceKey]);
            $isNew = false;
            if (!$entry instanceof ChronicleEntry) {
                $entry = new ChronicleEntry();
                $entry->setSourceKey($sourceKey);
                $entry->setShortHash($this->hashGenerator->generateUnique());
                $isNew = true;
            }

            $title = trim((string) ($row['title'] ?? 'Без названия'));
            $entry->setTitle('' !== $title ? $title : 'Без названия');
            $entry->setSlug($this->uniqueSlug((string) ($row['slug_hint'] ?? $title), $entry->getId()));
            $entry->setLede(isset($row['lede']) && \is_string($row['lede']) ? $row['lede'] : null);

            $status = ChronicleStatus::tryFrom((string) ($row['status'] ?? 'draft')) ?? ChronicleStatus::Draft;
            $entry->setStatus($status);

            if (\array_key_exists('unlisted', $row)) {
                $entry->setIsUnlisted((bool) $row['unlisted']);
            }

            if (\array_key_exists('admin_only', $row)) {
                $entry->setIsAdminOnly((bool) $row['admin_only']);
            }

            $publishedAt = $this->parseDate((string) ($row['date'] ?? ''));
            if (null !== $publishedAt) {
                // Original post date for both published and drafts (admin sorting / «Дата публикации»).
                $entry->setPublishedAt($publishedAt);
                if ($isNew) {
                    $entry->setCreatedAt($publishedAt);
                }
            }

            $eraSlug = $row['era'] ?? null;
            $entry->setEra(\is_string($eraSlug) && isset($eraCache[$eraSlug]) ? $eraCache[$eraSlug] : null);

            $seriesSlug = $row['series'] ?? null;
            $entry->setSeries(\is_string($seriesSlug) && isset($seriesCache[$seriesSlug]) ? $seriesCache[$seriesSlug] : null);

            if ($isNew || $syncTags) {
                $entry->getTags()->clear();
                if (isset($row['tags']) && \is_array($row['tags'])) {
                    foreach ($row['tags'] as $tagSlug) {
                        if (!\is_string($tagSlug)) {
                            continue;
                        }
                        if (!isset($tagCache[$tagSlug])) {
                            $tag = new ChronicleTag();
                            $tag->setSlug($tagSlug);
                            $tag->setName($tagSlug);
                            $this->em->persist($tag);
                            $tagCache[$tagSlug] = $tag;
                        }
                        $entry->addTag($tagCache[$tagSlug]);
                    }
                }
            }

            $this->replaceBlocks($entry, $row, $skipMedia);

            $entry->setReadingTimeMin($this->markdown->estimateReadingMinutes($entry));
            $entry->setUpdatedAt(new \DateTimeImmutable());
            $this->em->persist($entry);

            if ($isNew) {
                ++$created;
            } else {
                ++$updated;
            }

            if (0 === ($created + $updated) % 40) {
                $this->em->flush();
                $this->em->clear();
                // rebuild caches after clear
                $tagCache = [];
                foreach ($this->tags->findAll() as $tag) {
                    $tagCache[$tag->getSlug()] = $tag;
                }
                $eraCache = [];
                foreach ($this->eras->findAll() as $era) {
                    $eraCache[$era->getSlug()] = $era;
                }
                $seriesCache = [];
                foreach ($this->series->findAll() as $series) {
                    $seriesCache[$series->getSlug()] = $series;
                }
            }
        }

        fclose($handle);

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success(sprintf(
            'Импорт%s: просмотрено %d, создано %d, обновлено %d, пропущено %d',
            $dryRun ? ' (dry-run)' : '',
            $n,
            $created,
            $updated,
            $skipped,
        ));

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function replaceBlocks(ChronicleEntry $entry, array $row, bool $skipMedia): void
    {
        foreach ($entry->getBlocks()->toArray() as $block) {
            $entry->removeBlock($block);
        }

        $mediaDir = isset($row['media_dir']) && \is_string($row['media_dir']) ? $row['media_dir'] : null;
        $sort = 0;
        $blocks = $row['blocks'] ?? [];
        if (!\is_array($blocks)) {
            return;
        }

        $coverSet = false;
        foreach ($blocks as $blockRow) {
            if (!\is_array($blockRow)) {
                continue;
            }
            $type = ChronicleBlockType::tryFrom((string) ($blockRow['type'] ?? 'paragraph')) ?? ChronicleBlockType::Paragraph;
            $block = new ChronicleBlock();
            $block->setType($type);
            $block->setSortOrder($sort);
            $sort += 10;

            if (isset($blockRow['body']) && \is_string($blockRow['body'])) {
                // Strip leftover markdown image embeds (VK exports leave ![](media/…)).
                $body = preg_replace('/!\[[^\]]*]\([^)]+\)/', '', $blockRow['body']) ?? $blockRow['body'];
                $body = trim(preg_replace("/\n{3,}/", "\n\n", $body) ?? $body);
                $block->setBody($body);
            }
            if (isset($blockRow['calloutStyle']) && \is_string($blockRow['calloutStyle'])) {
                $block->setCalloutStyle($blockRow['calloutStyle']);
            }
            if (isset($blockRow['headingLevel'])) {
                $block->setHeadingLevel((int) $blockRow['headingLevel']);
            }
            if (isset($blockRow['author']) && \is_string($blockRow['author'])) {
                $block->setAuthor($blockRow['author']);
            }
            if (isset($blockRow['caption']) && \is_string($blockRow['caption'])) {
                $block->setCaption($blockRow['caption']);
            }

            // Always resolve media for image/gallery blocks. --skip-media must not
            // leave empty image_path (that wiped VK covers after title-only sync).
            if (null !== $mediaDir) {
                if (ChronicleBlockType::Image === $type) {
                    $sourcePath = isset($blockRow['sourcePath']) && \is_string($blockRow['sourcePath'])
                        ? $blockRow['sourcePath']
                        : null;
                    if (null !== $sourcePath) {
                        // Block body always references inline/; cover is a separate copy.
                        $inlineName = $this->copyImage($mediaDir, $sourcePath, 'chronicle/inline');
                        if (null !== $inlineName) {
                            $block->setImagePath($inlineName);
                            $block->setAlt(isset($blockRow['alt']) && \is_string($blockRow['alt']) ? $blockRow['alt'] : null);
                            if (!$coverSet) {
                                // Cover must live under covers/ (admin hardcodes that dir).
                                $coverName = $this->copyImage($mediaDir, $sourcePath, 'chronicle/covers');
                                if (null === $coverName) {
                                    $coverName = $this->duplicateUpload($inlineName, 'chronicle/inline', 'chronicle/covers');
                                }
                                if (null !== $coverName) {
                                    $entry->setCoverImagePath($coverName);
                                    $coverSet = true;
                                }
                            }
                        }
                    }
                }

                if (ChronicleBlockType::Gallery === $type && isset($blockRow['images']) && \is_array($blockRow['images'])) {
                    $imgSort = 0;
                    foreach ($blockRow['images'] as $imageRow) {
                        if (!\is_array($imageRow)) {
                            continue;
                        }
                        $sourcePath = isset($imageRow['sourcePath']) && \is_string($imageRow['sourcePath'])
                            ? $imageRow['sourcePath']
                            : null;
                        if (null === $sourcePath) {
                            continue;
                        }
                        if (!$coverSet) {
                            $coverName = $this->copyImage($mediaDir, $sourcePath, 'chronicle/covers');
                            if (null !== $coverName) {
                                $entry->setCoverImagePath($coverName);
                                $coverSet = true;
                            }
                        }
                        $galleryName = $this->copyImage($mediaDir, $sourcePath, 'chronicle/gallery');
                        if (null === $galleryName) {
                            continue;
                        }
                        $image = new \App\Entity\ChronicleBlockImage();
                        $image->setImagePath($galleryName);
                        $image->setAlt(isset($imageRow['alt']) && \is_string($imageRow['alt']) ? $imageRow['alt'] : null);
                        $image->setSortOrder($imgSort);
                        $imgSort += 10;
                        $block->addImage($image);
                    }
                }

                if (ChronicleBlockType::Audio === $type) {
                    $sourcePath = isset($blockRow['sourcePath']) && \is_string($blockRow['sourcePath'])
                        ? $blockRow['sourcePath']
                        : null;
                    if (null !== $sourcePath) {
                        $audioName = $this->copyAudio($mediaDir, $sourcePath);
                        if (null !== $audioName) {
                            $block->setVideoUrl('chronicle/audio/'.$audioName);
                        }
                    }
                }
            }

            $entry->addBlock($block);
        }

        // Drop stale covers when the corpus row has no images (text-only posts).
        if (!$coverSet && !$skipMedia) {
            $hasImageBlocks = false;
            foreach ($blocks as $blockRow) {
                if (!\is_array($blockRow)) {
                    continue;
                }
                $t = (string) ($blockRow['type'] ?? '');
                if ('image' === $t || 'gallery' === $t) {
                    $hasImageBlocks = true;
                    break;
                }
            }
            if (!$hasImageBlocks) {
                $entry->setCoverImagePath(null);
            }
        }
    }

    private function copyImage(string $mediaDir, string $relativePath, string $subdir): ?string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $base = basename($relativePath);
        $candidates = [
            $this->projectDir.'/'.$mediaDir.'/'.$relativePath,
            $this->projectDir.'/'.$relativePath,
            $this->projectDir.'/'.$mediaDir.'/'.$base,
            $this->projectDir.'/'.$mediaDir.'/media/'.$base,
            $this->projectDir.'/content/vk/'.$relativePath,
            $this->projectDir.'/content/vk/'.ltrim(preg_replace('#^\d{4}/#', '', $relativePath) ?? $relativePath, '/'),
        ];
        // Older corpus used sourcePath "2026/media/x.jpg" with media_dir "content/vk/2026".
        if (preg_match('#^\d{4}/(.+)$#', $relativePath, $m)) {
            $candidates[] = $this->projectDir.'/'.$mediaDir.'/'.$m[1];
            $candidates[] = $this->projectDir.'/content/vk/'.$relativePath;
        }
        $source = null;
        foreach (array_unique($candidates) as $candidate) {
            if (is_file($candidate)) {
                $source = $candidate;
                break;
            }
        }
        if (null === $source) {
            return null;
        }

        $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION) ?: 'jpg');
        if (!\in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return null;
        }

        $dir = $this->projectDir.'/public/uploads/'.$subdir;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }

        $filename = Uuid::v7()->toRfc4122().'.'.$ext;
        $target = $dir.'/'.$filename;
        if (!copy($source, $target)) {
            return null;
        }

        return $filename;
    }

    private function copyAudio(string $mediaDir, string $relativePath): ?string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $base = basename($relativePath);
        $candidates = [
            $this->projectDir.'/'.$mediaDir.'/'.$relativePath,
            $this->projectDir.'/'.$relativePath,
            $this->projectDir.'/'.$mediaDir.'/'.$base,
            $this->projectDir.'/'.$mediaDir.'/media/'.$base,
            $this->projectDir.'/content/vk/'.$relativePath,
            $this->projectDir.'/content/vk/'.ltrim(preg_replace('#^\d{4}/#', '', $relativePath) ?? $relativePath, '/'),
        ];
        if (preg_match('#^\d{4}/(.+)$#', $relativePath, $m)) {
            $candidates[] = $this->projectDir.'/'.$mediaDir.'/'.$m[1];
            $candidates[] = $this->projectDir.'/content/vk/'.$relativePath;
        }
        $source = null;
        foreach (array_unique($candidates) as $candidate) {
            if (is_file($candidate)) {
                $source = $candidate;
                break;
            }
        }
        if (null === $source) {
            return null;
        }

        $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION) ?: 'mp3');
        if (!\in_array($ext, ['mp3', 'm4a', 'ogg', 'wav', 'opus', 'aac'], true)) {
            return null;
        }

        $dir = $this->projectDir.'/public/uploads/chronicle/audio';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }

        $filename = Uuid::v7()->toRfc4122().'.'.$ext;
        $target = $dir.'/'.$filename;
        if (!copy($source, $target)) {
            return null;
        }

        return $filename;
    }

    private function duplicateUpload(string $filename, string $fromSubdir, string $toSubdir): ?string
    {
        $source = $this->projectDir.'/public/uploads/'.$fromSubdir.'/'.$filename;
        if (!is_file($source)) {
            return null;
        }

        $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION) ?: 'jpg');
        $dir = $this->projectDir.'/public/uploads/'.$toSubdir;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }

        $targetName = Uuid::v7()->toRfc4122().'.'.$ext;
        $target = $dir.'/'.$targetName;
        if (!copy($source, $target)) {
            return null;
        }

        return $targetName;
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ('' === $value) {
            return null;
        }
        try {
            return new \DateTimeImmutable(substr($value, 0, 19));
        } catch (\Exception) {
            return null;
        }
    }

    private function uniqueSlug(string $hint, ?int $excludeId): string
    {
        $base = strtolower($this->slugger->slug($hint)->toString());
        if ('' === $base) {
            $base = 'entry';
        }
        $base = substr($base, 0, 100);
        $slug = $base;
        $i = 2;
        while ($this->slugTaken($slug, $excludeId)) {
            $slug = $base.'-'.$i;
            ++$i;
        }

        return $slug;
    }

    private function slugTaken(string $slug, ?int $excludeId): bool
    {
        $existing = $this->entries->findOneBy(['slug' => $slug]);
        if (!$existing instanceof ChronicleEntry) {
            return false;
        }

        return $existing->getId() !== $excludeId;
    }
}
