<?php

declare(strict_types=1);

namespace App\Command;

use App\Content\ChronicleSeedContent;
use App\Entity\ChronicleBlock;
use App\Entity\ChronicleEntry;
use App\Entity\ChronicleEra;
use App\Entity\ChronicleTag;
use App\Enum\ChronicleBlockType;
use App\Enum\ChronicleStatus;
use App\Repository\ChronicleEntryRepository;
use App\Repository\ChronicleEraRepository;
use App\Repository\ChronicleTagRepository;
use App\Service\ChronicleHashGenerator;
use App\Service\ChronicleMarkdownRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:chronicle:seed',
    description: 'Создаёт эпохи и пример записи из VK-поста (полный текст, без правок)',
)]
final class SeedChronicleCommand extends Command
{
    /**
     * Canonical eras live in config/content/catalog.json (app:chronicle:seed-meta).
     * Kept here for backward-compatible seed of the sample VK entry.
     *
     * @var list<array{title: string, slug: string, order: int, color?: string, period?: string}>
     */
    private const ERAS = [
        ['title' => 'Щорса', 'slug' => 'shchorsa', 'order' => 10, 'period' => '11.2012–11.2015'],
        ['title' => 'Добролюбова', 'slug' => 'dobrolyubova', 'order' => 20, 'period' => '03.2016–09.2016'],
        ['title' => 'Рябиновое государство', 'slug' => 'ryabinovoe-gosudarstvo', 'order' => 30, 'color' => '#8b4513', 'period' => '09.2016–01.2017'],
        ['title' => 'Ленина пять', 'slug' => 'leninapyat', 'order' => 40, 'period' => '10.2016–06.2018 · 08.2025–н.в.'],
        ['title' => 'Краснолесье', 'slug' => 'krasnolesye', 'order' => 50, 'period' => '07.2018–06.2019'],
        ['title' => 'ЖБИ', 'slug' => 'zhbi', 'order' => 60, 'period' => '07.2019–02.2020'],
        ['title' => 'Народная воля', 'slug' => 'narodnaya-volya', 'order' => 70, 'period' => '03.2020–09.2020'],
        ['title' => 'Странствия', 'slug' => 'stranstviya', 'order' => 75, 'period' => '07.2020–10.2020'],
        ['title' => 'Татарстан', 'slug' => 'tatarstan', 'order' => 80, 'period' => '10.2020–08.2021'],
        ['title' => 'Ботаника', 'slug' => 'botanika', 'order' => 85, 'period' => '09.2021–10.2021'],
        ['title' => 'Рассветная', 'slug' => 'rassvetnaya', 'order' => 90, 'color' => '#e8a849', 'period' => '10.2021–07.2025'],
        ['title' => 'Зеленоград', 'slug' => 'zelenograd', 'order' => 95, 'period' => '04.2024 · 06.2024–08.2024'],
        ['title' => 'Коммуна', 'slug' => 'kommuna', 'order' => 100, 'color' => '#c87828', 'period' => '26.12.2025–13.05.2026 · внутри Ленина пять'],
        ['title' => 'Делайогу', 'slug' => 'delaiogu', 'order' => 110],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ChronicleEraRepository $eras,
        private readonly ChronicleTagRepository $tags,
        private readonly ChronicleEntryRepository $entries,
        private readonly ChronicleHashGenerator $hashGenerator,
        private readonly ChronicleMarkdownRenderer $markdown,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $eraMap = [];
        foreach (self::ERAS as $row) {
            $era = $this->eras->findOneBy(['slug' => $row['slug']]) ?? new ChronicleEra();
            $era->setTitle($row['title']);
            $era->setSlug($row['slug']);
            $era->setSortOrder($row['order']);
            if (isset($row['color'])) {
                $era->setColor($row['color']);
            }
            if (isset($row['period'])) {
                $era->setPeriodLabel($row['period']);
            }
            $this->em->persist($era);
            $eraMap[$row['slug']] = $era;
        }

        $tagLeto = $this->tags->findOneBy(['slug' => 'leto']) ?? new ChronicleTag();
        $tagLeto->setName('лето');
        $tagLeto->setSlug('leto');
        $this->em->persist($tagLeto);

        $tagGorod = $this->tags->findOneBy(['slug' => 'gorod']) ?? new ChronicleTag();
        $tagGorod->setName('город');
        $tagGorod->setSlug('gorod');
        $this->em->persist($tagGorod);

        $entry = $this->entries->findOneBy(['slug' => ChronicleSeedContent::ENTRY_SLUG]) ?? new ChronicleEntry();
        if (null === $entry->getId()) {
            $entry->setShortHash($this->hashGenerator->generateUnique());
        }

        $entry->setTitle(ChronicleSeedContent::ENTRY_TITLE);
        $entry->setSlug(ChronicleSeedContent::ENTRY_SLUG);
        $entry->setLede(ChronicleSeedContent::ledeFromBody());
        $entry->setExcerpt(null);
        $entry->setEra($eraMap['ryabinovoe-gosudarstvo'] ?? null);
        $entry->getTags()->clear();
        $entry->addTag($tagLeto);
        $entry->addTag($tagGorod);
        $entry->setStatus(ChronicleStatus::Published);
        $entry->setPublishedAt(new \DateTimeImmutable('2026-07-15 21:58:00'));
        $entry->setIsFeatured(true);

        $this->replaceBodyBlock($entry);

        $entry->setReadingTimeMin($this->markdown->estimateReadingMinutes($entry));
        $this->em->persist($entry);
        $this->em->flush();

        $io->success(sprintf(
            'Хроника: %d эпох, пример /chronicle/%s, короткая /p/%s',
            \count(self::ERAS),
            $entry->getSlug(),
            $entry->getShortHash(),
        ));

        return Command::SUCCESS;
    }

    private function replaceBodyBlock(ChronicleEntry $entry): void
    {
        foreach ($entry->getBlocks()->toArray() as $block) {
            $entry->removeBlock($block);
        }

        $block = new ChronicleBlock();
        $block->setType(ChronicleBlockType::Paragraph);
        $block->setBody(ChronicleSeedContent::ENTRY_BODY);
        $block->setSortOrder(0);
        $entry->addBlock($block);
    }
}
