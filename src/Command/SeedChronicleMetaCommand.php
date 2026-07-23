<?php

declare(strict_types=1);

namespace App\Command;

use App\Content\ContentCatalog;
use App\Entity\ChronicleEra;
use App\Entity\ChronicleSeries;
use App\Entity\ChronicleTag;
use App\Repository\ChronicleEraRepository;
use App\Repository\ChronicleSeriesRepository;
use App\Repository\ChronicleTagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:chronicle:seed-meta',
    description: 'Синхронизирует эпохи, серии и теги из config/content/catalog.json',
)]
final class SeedChronicleMetaCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ContentCatalog $catalog,
        private readonly ChronicleEraRepository $eras,
        private readonly ChronicleSeriesRepository $series,
        private readonly ChronicleTagRepository $tags,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $eraCount = 0;
        foreach ($this->catalog->eras() as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ('' === $slug) {
                continue;
            }
            $era = $this->eras->findOneBy(['slug' => $slug]) ?? new ChronicleEra();
            $era->setSlug($slug);
            $era->setTitle((string) ($row['title'] ?? $slug));
            $era->setSortOrder((int) ($row['order'] ?? 0));
            if (isset($row['period_label'])) {
                $era->setPeriodLabel(\is_string($row['period_label']) ? $row['period_label'] : null);
            }
            if (isset($row['color']) && \is_string($row['color'])) {
                $era->setColor($row['color']);
            }
            if (isset($row['nested_in']) && \is_string($row['nested_in'])) {
                $desc = $era->getDescription() ?? '';
                if (!str_contains($desc, 'внутри')) {
                    $era->setDescription(trim($desc."\nВложенный эпизод внутри: ".$row['nested_in']));
                }
            }
            $this->em->persist($era);
            ++$eraCount;
        }

        // Drop obsolete slug if still present
        $legacy = $this->eras->findOneBy(['slug' => 'tatarcha']);
        if ($legacy instanceof ChronicleEra) {
            $legacy->setSlug('tatarstan');
            $legacy->setTitle('Татарстан');
            $legacy->setPeriodLabel('10.2020–08.2021');
            $this->em->persist($legacy);
        }

        $seriesCount = 0;
        foreach ($this->catalog->series() as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ('' === $slug) {
                continue;
            }
            $series = $this->series->findOneBy(['slug' => $slug]) ?? new ChronicleSeries();
            $series->setSlug($slug);
            $series->setTitle((string) ($row['title'] ?? $slug));
            $series->setSortOrder((int) ($row['order'] ?? 0));
            if (isset($row['description']) && \is_string($row['description'])) {
                $series->setDescription($row['description']);
            }
            $this->em->persist($series);
            ++$seriesCount;
        }

        $tagCount = 0;
        $seenTagSlugs = [];
        foreach ([...$this->catalog->themeTags(), ...$this->catalog->channelTags()] as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ('' === $slug || isset($seenTagSlugs[$slug])) {
                continue;
            }
            $seenTagSlugs[$slug] = true;
            $tag = $this->tags->findOneBy(['slug' => $slug]) ?? new ChronicleTag();
            $tag->setSlug($slug);
            $tag->setName((string) ($row['name'] ?? $slug));
            $this->em->persist($tag);
            ++$tagCount;
        }

        $this->em->flush();

        $io->success(sprintf('Мета: %d эпох, %d серий, %d тегов', $eraCount, $seriesCount, $tagCount));

        return Command::SUCCESS;
    }
}
