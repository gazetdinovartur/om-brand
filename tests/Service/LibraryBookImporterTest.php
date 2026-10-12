<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\LibraryBook;
use App\Library\LibraryBookImportResult;
use App\Service\LibraryBookImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LibraryBookImporterTest extends KernelTestCase
{
    private ?string $sourcePath = null;

    protected function tearDown(): void
    {
        if (null !== $this->sourcePath) {
            @unlink($this->sourcePath);
        }
        parent::tearDown();
    }

    public function testNewImportReplaceGuardAndChecksumNoOp(): void
    {
        self::bootKernel();
        $importer = self::getContainer()->get(LibraryBookImporter::class);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $repository = $em->getRepository(LibraryBook::class);

        $slug = 'library-importer-test-'.bin2hex(random_bytes(5));
        $this->sourcePath = sys_get_temp_dir().'/'.$slug.'.txt';
        file_put_contents($this->sourcePath, $this->fixtureText('Первый абзац.'));

        $created = $importer->import($this->sourcePath, slug: $slug, allowReplace: false);
        self::assertSame(LibraryBookImportResult::OUTCOME_CREATED, $created->outcome);
        self::assertCount(1, $repository->findBy(['slug' => $slug]));

        try {
            $importer->import($this->sourcePath, slug: $slug, allowReplace: false);
            self::fail('Expected replace guard.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('уже есть', $exception->getMessage());
        }

        $unchanged = $importer->import($this->sourcePath, slug: $slug, allowReplace: true);
        self::assertSame(LibraryBookImportResult::OUTCOME_UNCHANGED, $unchanged->outcome);

        file_put_contents($this->sourcePath, $this->fixtureText('Обновлённый абзац.'));
        $updated = $importer->import($this->sourcePath, slug: $slug, allowReplace: true);
        self::assertSame(LibraryBookImportResult::OUTCOME_UPDATED, $updated->outcome);

        $em->clear();
        $book = $repository->findOneBy(['slug' => $slug]);
        self::assertInstanceOf(LibraryBook::class, $book);
        self::assertCount(3, $book->getChapters());
        self::assertStringContainsString('Обновлённый абзац', (string) json_encode($book->getChapters()->first()?->getBlocks(), \JSON_UNESCAPED_UNICODE));
    }

    private function fixtureText(string $introParagraph): string
    {
        return <<<TEXT
Автор Тестовый

"ТЕСТОВАЯ КНИГА"

Введение

{$introParagraph}

Глава 1. Проверка

1.1. Подраздел.

Текст главы.

Заключение

Финальный абзац.
TEXT;
    }
}
