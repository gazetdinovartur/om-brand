<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\LibraryBook;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ImportLibraryBookCommandTest extends KernelTestCase
{
    private ?string $sourcePath = null;

    protected function tearDown(): void
    {
        if (null !== $this->sourcePath) {
            @unlink($this->sourcePath);
        }
        parent::tearDown();
    }

    public function testDryRunDoesNotWriteAndRepeatedImportIsIdempotent(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);
        $application->setAutoExit(false);
        $tester = new CommandTester($application->find('app:library:import'));
        $slug = 'library-import-test-'.bin2hex(random_bytes(5));
        $this->sourcePath = sys_get_temp_dir().'/'.$slug.'.txt';
        file_put_contents($this->sourcePath, $this->fixtureText());

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $repository = $em->getRepository(LibraryBook::class);

        $status = $tester->execute([
            'path' => $this->sourcePath,
            '--slug' => $slug,
            '--dry-run' => true,
        ]);

        self::assertSame(0, $status);
        self::assertNull($repository->findOneBy(['slug' => $slug]));

        $tester->execute(['path' => $this->sourcePath, '--slug' => $slug]);
        $tester->execute(['path' => $this->sourcePath, '--slug' => $slug]);
        $em->clear();

        $books = $repository->findBy(['slug' => $slug]);
        self::assertCount(1, $books);
        self::assertCount(3, $books[0]->getChapters());
        self::assertStringContainsString('изменений нет', $tester->getDisplay());
    }

    private function fixtureText(): string
    {
        return <<<'TEXT'
Автор Тестовый

"ТЕСТОВАЯ КНИГА"

Введение

Первый абзац.

Глава 1. Проверка

1.1. Подраздел.

Текст главы.

Заключение

Финальный абзац.
TEXT;
    }
}
