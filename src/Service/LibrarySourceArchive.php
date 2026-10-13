<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Keeps original book sources (e.g. PDF fallback) under private uploads.
 */
final class LibrarySourceArchive
{
    public function __construct(
        #[Autowire('%app.private_uploads_directory%')]
        private readonly string $privateUploadsDirectory,
    ) {
    }

    public function storeBookSource(string $absolutePath, string $slug, string $extension): string
    {
        $targetDir = rtrim($this->privateUploadsDirectory, '/').'/library-sources';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Не удалось создать каталог library-sources.');
        }

        $safeSlug = preg_replace('/[^a-z0-9\-]+/i', '-', $slug) ?: 'book';
        $relative = sprintf('library-sources/%s.%s', $safeSlug, $extension);
        $target = rtrim($this->privateUploadsDirectory, '/').'/'.$relative;

        if (!@copy($absolutePath, $target)) {
            throw new \RuntimeException('Не удалось сохранить исходник книги.');
        }

        return $relative;
    }

    public function absolutePath(string $relative): ?string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if (!str_starts_with($relative, 'library-sources/')) {
            return null;
        }

        $full = rtrim($this->privateUploadsDirectory, '/').'/'.$relative;
        $root = realpath(rtrim($this->privateUploadsDirectory, '/').'/library-sources');
        $resolved = realpath($full);
        if (false === $root || false === $resolved || !str_starts_with($resolved, $root.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return is_file($resolved) ? $resolved : null;
    }
}
