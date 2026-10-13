<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final class LibraryImportUploadStorage
{
    private const MAX_SIZE = 20 * 1024 * 1024;

    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = ['txt', 'zip', 'fb2', 'epub', 'md', 'markdown', 'pdf'];

    public function __construct(
        #[Autowire('%app.private_uploads_directory%')]
        private readonly string $privateUploadsDirectory,
    ) {
    }

    /**
     * Stores the upload under var/private/uploads/library-import/ and returns an absolute path.
     */
    public function store(UploadedFile $file): string
    {
        if ($file->getSize() > self::MAX_SIZE) {
            throw new FileException('Файл слишком большой. Максимум 20 МБ.');
        }

        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->guessExtension()));
        if ('markdown' === $extension) {
            $extension = 'md';
        }
        if (!\in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new FileException('Поддерживаются TXT, MD, FB2, EPUB, PDF и ZIP с txt/md/fb2.');
        }

        $targetDir = rtrim($this->privateUploadsDirectory, '/').'/library-import';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new FileException('Не удалось создать каталог для загрузки.');
        }

        $filename = sprintf('%s.%s', Uuid::v7()->toRfc4122(), $extension);
        $file->move($targetDir, $filename);

        return $targetDir.'/'.$filename;
    }

    public function remove(?string $absolutePath): void
    {
        if (null === $absolutePath || '' === $absolutePath) {
            return;
        }

        $root = realpath(rtrim($this->privateUploadsDirectory, '/').'/library-import');
        $resolved = realpath($absolutePath);
        if (false === $root || false === $resolved || !str_starts_with($resolved, $root.DIRECTORY_SEPARATOR)) {
            return;
        }

        if (is_file($resolved)) {
            @unlink($resolved);
        }
    }
}
