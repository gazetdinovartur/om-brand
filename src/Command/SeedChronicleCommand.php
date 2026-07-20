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
    /** @var list<array{title: string, slug: string, order: int, color?: string}> */
    private const ERAS = [
        ['title' => 'Рассветная', 'slug' => 'rassvetnaya', 'order' => 10, 'color' => '#e8a849'],
        ['title' => 'Коммуна', 'slug' => 'kommuna', 'order' => 20, 'color' => '#c87828'],
        ['title' => 'Ленина пять', 'slug' => 'leninapyat', 'order' => 30],
        ['title' => 'Народная воля', 'slug' => 'narodnaya-volya', 'order' => 40],
        ['title' => 'Добролюбова', 'slug' => 'dobrolyubova', 'order' => 50],
        ['title' => 'Татарча', 'slug' => 'tatarcha', 'order' => 60],
        ['title' => 'Делайогу', 'slug' => 'delaiogu', 'order' => 70],
        ['title' => 'Краснолесье', 'slug' => 'krasnolesye', 'order' => 80],
        ['title' => 'Щорса', 'slug' => 'shchorsa', 'order' => 90],
        ['title' => 'ЖБИ', 'slug' => 'zhbi', 'order' => 100],
        ['title' => 'Рябиновое государство', 'slug' => 'ryabinovoe-gosudarstvo', 'order' => 110, 'color' => '#8b4513'],
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
