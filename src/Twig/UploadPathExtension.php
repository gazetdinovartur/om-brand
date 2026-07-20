<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class UploadPathExtension extends AbstractExtension
{
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('upload_path', $this->resolve(...)),
            new TwigFunction('chronicle_cover', $this->chronicleCover(...)),
            new TwigFunction('chronicle_image', $this->chronicleImage(...)),
        ];
    }

    /**
     * Resolves a stored upload path to a public-relative path under /uploads.
     *
     * @param string $directoryPrefix Directory under uploads/ when DB stores only the filename
     *                                (e.g. "cases", "cases/gallery", "cases/audio")
     */
    public function resolve(?string $storedPath, string $directoryPrefix = ''): ?string
    {
        if (null === $storedPath || '' === trim($storedPath)) {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', $storedPath), '/');
        if (str_starts_with($path, 'uploads/')) {
            return $path;
        }

        if (str_contains($path, '/')) {
            return 'uploads/'.$path;
        }

        $prefix = trim(str_replace('\\', '/', $directoryPrefix), '/');
        if ('' === $prefix) {
            return 'uploads/'.$path;
        }

        return 'uploads/'.$prefix.'/'.$path;
    }

    /**
     * Cover may live in covers/ (editor) or inline/ (corpus import) — resolve first existing file.
     */
    public function chronicleCover(?string $storedPath): ?string
    {
        if (null === $storedPath || '' === trim($storedPath)) {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', $storedPath), '/');
        if (str_starts_with($path, 'uploads/')) {
            return is_file($this->projectDir.'/public/'.$path) ? $path : null;
        }

        if (str_contains($path, '/')) {
            $rel = 'uploads/'.$path;

            return is_file($this->projectDir.'/public/'.$rel) ? $rel : null;
        }

        foreach (['chronicle/covers', 'chronicle/inline', 'chronicle/gallery'] as $dir) {
            $rel = 'uploads/'.$dir.'/'.$path;
            if (is_file($this->projectDir.'/public/'.$rel)) {
                return $rel;
            }
        }

        return null;
    }

    /**
     * Inline/gallery/cover-safe resolver for block images (import may store file only in one dir).
     *
     * @param list<string>|string $preferredDirs
     */
    public function chronicleImage(?string $storedPath, array|string $preferredDirs = ['chronicle/inline', 'chronicle/covers', 'chronicle/gallery']): ?string
    {
        if (null === $storedPath || '' === trim($storedPath)) {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', $storedPath), '/');
        if (str_starts_with($path, 'uploads/')) {
            return is_file($this->projectDir.'/public/'.$path) ? $path : null;
        }

        if (str_contains($path, '/')) {
            $rel = 'uploads/'.$path;

            return is_file($this->projectDir.'/public/'.$rel) ? $rel : null;
        }

        $dirs = \is_array($preferredDirs) ? $preferredDirs : [$preferredDirs];
        foreach ($dirs as $dir) {
            $rel = 'uploads/'.trim(str_replace('\\', '/', (string) $dir), '/').'/'.$path;
            if (is_file($this->projectDir.'/public/'.$rel)) {
                return $rel;
            }
        }

        return null;
    }
}
