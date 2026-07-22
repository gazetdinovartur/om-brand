<?php

declare(strict_types=1);

namespace App\Command;

use App\Content\ModularCasesContent;
use App\Entity\CaseStudy;
use App\Enum\CasePresentationMode;
use App\Repository\CaseStudyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Первичный импорт модульных кейсов.
 * По умолчанию — только INSERT (существующие slug пропускаются; БД — источник истины).
 * --force перезаписывает тексты из ModularCasesContent.
 * --purge-obsolete удаляет кейсы, которых больше нет в корпусе.
 */
#[AsCommand(
    name: 'app:cases:seed',
    description: 'Первичный импорт модульных кейсов (после — правки только в админке)',
)]
final class SeedModularCasesCommand extends Command
{
    /** @var list<string> */
    private const TEST_SLUGS = ['sacred-geometry-lab', 'om-player'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CaseStudyRepository $cases,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('area', null, InputOption::VALUE_REQUIRED, 'Только область 1–4 (дом/звук/студия/процессы)')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Перезаписать тексты существующих кейсов из ModularCasesContent')
            ->addOption('purge-test', null, InputOption::VALUE_NONE, 'Удалить тестовые кейсы sacred-geometry-lab и om-player')
            ->addOption('purge-obsolete', null, InputOption::VALUE_NONE, 'Удалить кейсы, которых нет в ModularCasesContent');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $areaFilter = $input->getOption('area');

        if (null !== $areaFilter && !in_array((int) $areaFilter, [1, 2, 3, 4], true)) {
            $io->error('Опция --area принимает значение 1, 2, 3 или 4.');

            return Command::FAILURE;
        }

        if ($input->getOption('purge-test')) {
            $purged = $this->purgeSlugs(self::TEST_SLUGS, $io);
            $io->note(sprintf('Удалено тестовых кейсов: %d', $purged));
        }

        if ($input->getOption('purge-obsolete')) {
            $keep = ModularCasesContent::slugs();
            $obsolete = [];
            foreach ($this->cases->findAll() as $case) {
                if (!\in_array($case->getSlug(), $keep, true)) {
                    $obsolete[] = $case->getSlug();
                }
            }
            $purged = $this->purgeSlugs($obsolete, $io);
            $io->note(sprintf('Удалено устаревших кейсов: %d', $purged));
        }

        $entries = ModularCasesContent::all();
        if (null !== $areaFilter) {
            $area = (int) $areaFilter;
            $entries = array_values(array_filter(
                $entries,
                static fn (array $row): bool => ($row['wave'] ?? 0) === $area,
            ));
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($entries as $row) {
            $slug = (string) $row['slug'];
            $existing = $this->cases->findOneBy(['slug' => $slug]);

            if (null !== $existing && !$force) {
                ++$skipped;
                $io->writeln(sprintf('  <comment>skip</comment> %s — уже в БД (используй --force для перезаписи)', $slug), OutputInterface::VERBOSITY_VERBOSE);

                continue;
            }

            $case = $existing ?? new CaseStudy();
            $isNew = null === $existing;

            $this->applyRow($case, $row);
            $this->em->persist($case);

            if ($isNew) {
                ++$created;
                $io->writeln(sprintf('  <info>+</info> %s', $slug));
            } else {
                ++$updated;
                $io->writeln(sprintf('  <info>~</info> %s (force)', $slug));
            }
        }

        $this->em->flush();

        $io->success(sprintf(
            'Кейсы: создано %d, обновлено %d, пропущено %d. Дальше — правки в админке.',
            $created,
            $updated,
            $skipped,
        ));

        return Command::SUCCESS;
    }

    /** @param list<string> $slugs */
    private function purgeSlugs(array $slugs, SymfonyStyle $io): int
    {
        $count = 0;
        foreach ($slugs as $slug) {
            $case = $this->cases->findOneBy(['slug' => $slug]);
            if (null === $case) {
                continue;
            }
            $this->em->remove($case);
            ++$count;
            $io->writeln(sprintf('  <fg=red>-</> %s', $slug));
        }
        if ($count > 0) {
            $this->em->flush();
        }

        return $count;
    }

    /** @param array<string, mixed> $row */
    private function applyRow(CaseStudy $case, array $row): void
    {
        $case->setSlug((string) $row['slug']);
        $case->setTitle((string) $row['title']);
        $case->setSummary((string) $row['summary']);
        $case->setOutcomeLine((string) $row['outcomeLine']);
        $case->setDomain((string) $row['domain']);
        $case->setRole((string) $row['role']);
        $case->setYear((int) $row['year']);
        $case->setStoryHook((string) $row['storyHook']);
        $case->setStoryBody((string) $row['storyBody']);
        $case->setStoryOutcome((string) $row['storyOutcome']);
        $case->setIsPublished((bool) $row['isPublished']);
        $case->setShowOnLanding((bool) $row['showOnLanding']);
        $case->setIsFeatured((bool) $row['isFeatured']);
        $case->setHasDetailPage((bool) $row['hasDetailPage']);
        $case->setSortOrder((int) $row['sortOrder']);

        $case->setStoryContext(null);
        $case->setStoryTurningPoint(null);
        $case->setStoryApproach(null);
        $case->setStoryReflection(null);
        $case->setQuote(null);
        $case->setQuoteAuthor(null);
        $case->setContent(null);

        $mode = CasePresentationMode::tryFrom((string) ($row['presentationMode'] ?? 'none'))
            ?? CasePresentationMode::None;
        $case->setPresentationMode($mode);

        $case->setOmTrackSlug($row['omTrackSlug'] ?? null);
        $case->setAudioTitle($row['audioTitle'] ?? null);
        $case->setPresentationIntro($row['presentationIntro'] ?? null);
        $case->setPresentationDuration($row['presentationDuration'] ?? null);
        $case->setVideoUrl(null);
        $case->setVideoTitle(null);
        $case->setAudioPath(null);
        $case->setAudioUrl(null);

        if (isset($row['seoTitle'])) {
            $case->setSeoTitle((string) $row['seoTitle']);
        }
        if (isset($row['seoDescription'])) {
            $case->setSeoDescription((string) $row['seoDescription']);
        }
    }
}
