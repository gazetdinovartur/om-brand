<?php

namespace App\Command;

use App\Content\LandingContent;
use App\Entity\ContentBlock;
use App\Entity\SiteSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:content:sync',
    description: 'Обновляет тексты лендинга из эталонного контента',
)]
final class SyncContentCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $repository = $this->entityManager->getRepository(ContentBlock::class);

        foreach (LandingContent::blocks() as $data) {
            $block = $repository->findOneBy(['slug' => $data['slug']]) ?? new ContentBlock();

            $block
                ->setSlug($data['slug'])
                ->setType($data['type'])
                ->setTitle($data['title'])
                ->setSubtitle($data['subtitle'])
                ->setBody($data['body'])
                ->setItems($data['items'])
                ->setSortOrder($data['sortOrder'])
                ->setIsVisible(true);

            if (null === $block->getId()) {
                $this->entityManager->persist($block);
            }
        }

        $this->syncSiteSettings();
        $this->entityManager->flush();

        $io->success('Контент лендинга обновлён.');

        return Command::SUCCESS;
    }

    private function syncSiteSettings(): void
    {
        $settings = $this->entityManager->getRepository(SiteSettings::class)->findOneBy([]);
        if (!$settings instanceof SiteSettings) {
            return;
        }

        $settings
            ->setName(LandingContent::personName())
            ->setTagline(LandingContent::metaDescription())
            ->setCity(LandingContent::headerSubtitle());
    }
}
