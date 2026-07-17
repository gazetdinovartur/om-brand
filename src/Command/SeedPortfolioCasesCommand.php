<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\CaseStudy;
use App\Entity\CaseStudyImage;
use App\Enum\CasePresentationMode;
use App\Repository\CaseStudyRepository;
use App\Service\ImageOptimizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:cases:seed-portfolio',
    description: 'Создаёт/обновляет кейсы Sacred Geometry Lab и OmPlayer',
)]
final class SeedPortfolioCasesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CaseStudyRepository $cases,
        private readonly ImageOptimizer $imageOptimizer,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $lab = $this->upsertLab();
        $music = $this->upsertMusic();
        $this->em->flush();

        $io->success(sprintf(
            'Кейсы готовы: /cases/%s и /cases/%s',
            $lab->getSlug(),
            $music->getSlug(),
        ));

        return Command::SUCCESS;
    }

    private function upsertLab(): CaseStudy
    {
        $case = $this->cases->findOneBy(['slug' => 'sacred-geometry-lab']) ?? new CaseStudy();
        $case->setTitle('Sacred Geometry Lab');
        $case->setSlug('sacred-geometry-lab');
        $case->setSummary('Голос и музыка становятся живой геометрией в реальном времени — лаборатория наблюдения, не диагностика.');
        $case->setOutcomeLine('Слышимое стало видимым: человек может увидеть свой процесс и унести мандалу с собой.');
        $case->setDomain('исследование · звук · геометрия');
        $case->setRole('идея, продукт, разработка');
        $case->setYear(2026);
        $case->setIsPublished(true);
        $case->setShowOnLanding(true);
        $case->setIsFeatured(true);
        $case->setHasDetailPage(true);
        $case->setSortOrder(10);

        $case->setStoryHook('Нужно было место спросить себя «как ты сейчас?» — и увидеть ответ формой, а не очередной визуализатор.');
        $case->setStoryContext(null);
        $case->setStoryTurningPoint(null);
        $case->setStoryApproach(null);
        $case->setStoryBody(
            "Собрал lab.arturlun.ru: голос и ритм в реальном времени становятся геометрией. "
            ."Сессия с калибровкой, этапами и экспортом. Сервис не домысливает — только то, что звучит, включая тишину. "
            ."Узор можно сохранить и открыть снова."
        );
        $case->setStoryOutcome(
            "Есть живая лаборатория для практики. Можно привести человека и сказать: побудь здесь."
        );
        $case->setStoryReflection(null);
        $case->setQuote(null);
        $case->setQuoteAuthor(null);

        $case->setPresentationMode(CasePresentationMode::None);
        $case->setSeoTitle('Sacred Geometry Lab — кейс · Артур Лун');
        $case->setSeoDescription('Как голос и музыка становятся живой геометрией: история Sacred Geometry Lab — от вопроса «как ты сейчас?» до рабочей лаборатории.');

        $this->attachCover($case, 'sgl-cover.png');
        $this->replaceGallery($case, [
            ['file' => 'sgl-about.png', 'caption' => 'Страница «О проекте»', 'order' => 10],
        ]);

        $this->em->persist($case);

        return $case;
    }

    private function upsertMusic(): CaseStudy
    {
        $case = $this->cases->findOneBy(['slug' => 'om-player']) ?? new CaseStudy();
        $case->setTitle('OmPlayer');
        $case->setSlug('om-player');
        $case->setSummary('Свободная музыкальная площадка и встраиваемый плеер на своём домене — без аккаунта, без приложения, просто побыть, пока песня длится.');
        $case->setOutcomeLine('Музыка снова живёт на своём сайте: каталог, плеер и API — без чужих площадок и рекламных алгоритмов.');
        $case->setDomain('музыка · платформа · web component');
        $case->setRole('продукт, разработка, плеер');
        $case->setYear(2026);
        $case->setIsPublished(true);
        $case->setShowOnLanding(true);
        $case->setIsFeatured(false);
        $case->setHasDetailPage(true);
        $case->setSortOrder(20);

        $case->setStoryHook('Своя музыка не должна жить только в чужом приложении — с аккаунтом, лентой и чужими правилами.');
        $case->setStoryContext(null);
        $case->setStoryTurningPoint(null);
        $case->setStoryApproach(null);
        $case->setStoryBody(
            "Собрал music.arturlun.ru и Web Component <om-player>: каталог, мини-плеер, страница альбома и embed. "
            ."Один скрипт — три режима. Без регистрации для слушателя, со своей админкой и API."
        );
        $case->setStoryOutcome(
            "Площадка открыта: можно слушать, встраивать и отдавать другим как основу для своей сцены."
        );
        $case->setStoryReflection(null);
        $case->setQuote(null);
        $case->setQuoteAuthor(null);

        $case->setPresentationMode(CasePresentationMode::Audio);
        $case->setPresentationIntro('Можно послушать прямо здесь — тот же OmPlayer, что на music.arturlun.ru');
        $case->setPresentationDuration('~2 мин');
        $case->setOmTrackSlug('iz-etogo-mesta');
        $case->setAudioTitle('из этого места');

        $case->setSeoTitle('OmPlayer — кейс · Артур Лун');
        $case->setSeoDescription('Как собрал свободную музыкальную площадку и Web Component <om-player>: каталог, три режима плеера, API и своё место для звука.');

        $this->attachCover($case, 'om-cover.png');
        $this->replaceGallery($case, [
            ['file' => 'om-about.png', 'caption' => 'Страница «О проекте»', 'order' => 10],
        ]);

        $this->em->persist($case);

        return $case;
    }

    private function attachCover(CaseStudy $case, string $filename): void
    {
        $source = $this->projectDir.'/public/uploads/cases/'.$filename;
        if (!is_file($source)) {
            return;
        }

        $relative = 'cases/'.$filename;
        if (str_ends_with(strtolower($filename), '.webp')) {
            $case->setCoverImagePath(basename($filename));

            return;
        }

        $result = $this->imageOptimizer->optimizeToWebp($relative, maxWidth: 1400, thumbWidth: 640);
        $case->setCoverImagePath(basename($result['path']));
    }

    /**
     * @param list<array{file: string, caption: string, order: int}> $items
     */
    private function replaceGallery(CaseStudy $case, array $items): void
    {
        foreach ($case->getGalleryImages()->toArray() as $existing) {
            $case->removeGalleryImage($existing);
            $this->em->remove($existing);
        }

        foreach ($items as $item) {
            $source = $this->projectDir.'/public/uploads/cases/gallery/'.$item['file'];
            if (!is_file($source)) {
                continue;
            }

            $relative = 'cases/gallery/'.$item['file'];
            $filename = $item['file'];
            if (!str_ends_with(strtolower($filename), '.webp')) {
                $result = $this->imageOptimizer->optimizeToWebp($relative, maxWidth: 1400, thumbWidth: 640);
                $filename = basename($result['path']);
            }

            $image = new CaseStudyImage();
            $image->setImagePath($filename);
            $image->setCaption($item['caption']);
            $image->setSortOrder($item['order']);
            $case->addGalleryImage($image);
        }
    }
}
