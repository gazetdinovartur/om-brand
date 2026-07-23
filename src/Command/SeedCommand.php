<?php

namespace App\Command;

use App\Content\LandingContent;
use App\Entity\AdminUser;
use App\Entity\ContentBlock;
use App\Entity\SiteSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed',
    description: 'Заполняет начальные настройки, контент лендинга и администратора',
)]
final class SeedCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        #[Autowire('%env(ADMIN_EMAIL)%')]
        private readonly string $adminEmail,
        #[Autowire('%env(ADMIN_PASSWORD)%')]
        private readonly string $adminPassword,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ('' === trim($this->adminPassword) || 'admin' === $this->adminPassword || 'change_me_strong_password' === $this->adminPassword) {
            $io->error('Задайте надёжный ADMIN_PASSWORD в .env (см. .env.example).');

            return Command::FAILURE;
        }

        if ('' === trim($this->adminEmail)) {
            $io->error('Задайте ADMIN_EMAIL в .env.');

            return Command::FAILURE;
        }

        $this->seedSettings();
        $this->syncContentBlocks();
        $this->seedAdmin();

        $this->entityManager->flush();

        $io->success('Seed выполнен: настройки, контент и администратор готовы.');

        return Command::SUCCESS;
    }

    private function seedSettings(): void
    {
        if ($this->entityManager->getRepository(SiteSettings::class)->count([]) > 0) {
            return;
        }

        $settings = (new SiteSettings())
            ->setName(LandingContent::personName())
            ->setTagline(LandingContent::metaDescription())
            ->setCity(LandingContent::headerSubtitle())
            ->setFormSuccessMessage('Вижу Ваш запрос. Скоро выйду на связь.');

        $this->entityManager->persist($settings);
    }

    private function syncContentBlocks(): void
    {
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
    }

    private function seedAdmin(): void
    {
        if ($this->entityManager->getRepository(AdminUser::class)->count([]) > 0) {
            return;
        }

        $admin = (new AdminUser())
            ->setEmail($this->adminEmail)
            ->setRoles(['ROLE_ADMIN']);

        $admin->setPassword($this->passwordHasher->hashPassword($admin, $this->adminPassword));

        $this->entityManager->persist($admin);
    }
}
