#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Entity\ChronicleEntry;
use App\Enum\ChronicleBlockType;
use App\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';

$kernel = new Kernel('dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$repo = $em->getRepository(ChronicleEntry::class);

/** @var list<ChronicleEntry> $entries */
$entries = $repo->createQueryBuilder('e')
    ->leftJoin('e.blocks', 'b')->addSelect('b')
    ->where('e.sourceKey LIKE :sk OR EXISTS (SELECT 1 FROM App\Entity\ChronicleBlock ab WHERE ab.entry = e AND ab.type = :audio AND ab.videoUrl IS NOT NULL)')
    ->setParameter('sk', 'tg:mirror:%')
    ->setParameter('audio', ChronicleBlockType::Audio)
    ->orderBy('e.publishedAt', 'ASC')
    ->getQuery()
    ->getResult();

$uploads = dirname(__DIR__).'/public/uploads/';
$base = 'http://localhost:8085';

$fmt = static function (int $bytes): string {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1).' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 1).' KB';
    }

    return $bytes.' B';
};

$entrySize = static function (ChronicleEntry $e) use ($uploads): int {
    $size = strlen($e->getTitle()) + strlen($e->getLede() ?? '') + strlen($e->getExcerpt() ?? '');
    foreach ($e->getBlocks() as $b) {
        $size += strlen($b->getBody() ?? '') + strlen($b->getCaption() ?? '');
        $videoUrl = $b->getVideoUrl();
        if (null !== $videoUrl && str_starts_with($videoUrl, 'chronicle/audio/')) {
            $path = $uploads.$videoUrl;
            if (is_file($path)) {
                $size += (int) filesize($path);
            }
        }
        if (null !== $b->getImagePath()) {
            foreach (['chronicle/inline/', 'chronicle/covers/', 'chronicle/gallery/'] as $dir) {
                $path = $uploads.$dir.$b->getImagePath();
                if (is_file($path)) {
                    $size += (int) filesize($path);
                    break;
                }
            }
        }
    }

    return $size;
};

$lines = [
    '# Посты mirror + VK с аудио',
    '',
    '| Название | Канал | Аудио | Видимость | Размер | URL | Короткая | Админка |',
    '|---|---|---|---|---|---|---|---|',
];

foreach ($entries as $e) {
    $isMirror = str_starts_with((string) $e->getSourceKey(), 'tg:mirror:');
    $hasAudio = $e->getBlocks()->exists(
        static fn (int $k, $b): bool => ChronicleBlockType::Audio === $b->getType() && null !== $b->getVideoUrl()
    );
    if (!$isMirror && !$hasAudio) {
        continue;
    }

    $name = str_replace('|', '/', $e->getTitle());
    $lines[] = sprintf(
        '| %s | %s | %s | %s | %s | %s | %s | %s |',
        $name,
        $e->getSeries()?->getTitle() ?? '',
        $hasAudio ? 'да' : '',
        $e->isUnlisted() ? 'unlisted' : 'лента',
        $fmt($entrySize($e)),
        $base.'/chronicle/'.$e->getSlug(),
        $base.'/p/'.$e->getShortHash(),
        $base.'/admin/chronicle/editor/'.$e->getId(),
    );
}

$out = dirname(__DIR__).'/analysis/audio-posts-table.md';
file_put_contents($out, implode("\n", $lines)."\n");
echo 'Written '.(count($lines) - 4)." rows to {$out}\n";
