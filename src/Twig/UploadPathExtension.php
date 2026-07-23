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
            return $this->existingPublicRel($path);
        }

        if (str_contains($path, '/')) {
            return $this->existingPublicRel('uploads/'.$path) ?? 'uploads/'.$path;
        }

        $prefix = trim(str_replace('\\', '/', $directoryPrefix), '/');
        if ('' === $prefix) {
            return $this->existingPublicRel('uploads/'.$path) ?? 'uploads/'.$path;
        }

        return $this->existingPublicRel('uploads/'.$prefix.'/'.$path)
            ?? 'uploads/'.$prefix.'/'.$path;
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
            return $this->existingPublicRel($path);
        }

        if (str_contains($path, '/')) {
            return $this->existingPublicRel('uploads/'.$path);
        }

        foreach (['chronicle/covers', 'chronicle/inline', 'chronicle/gallery'] as $dir) {
            $rel = $this->existingPublicRel('uploads/'.$dir.'/'.$path);
            if (null !== $rel) {
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
            return $this->existingPublicRel($path);
        }

        if (str_contains($path, '/')) {
            return $this->existingPublicRel('uploads/'.$path);
        }

        $dirs = \is_array($preferredDirs) ? $preferredDirs : [$preferredDirs];
        foreach ($dirs as $dir) {
            $rel = $this->existingPublicRel('uploads/'.trim(str_replace('\\', '/', (string) $dir), '/').'/'.$path);
            if (null !== $rel) {
                return $rel;
            }
        }

        return null;
    }

    /**
     * Prefer the exact path; if optimizer left only .webp, fall back to it.
     */
    private function existingPublicRel(string $rel): ?string
    {
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        $absolute = $this->projectDir.'/public/'.$rel;
        if (is_file($absolute)) {
            return $rel;
        }

        if (preg_match('/\.(jpe?g|png|gif)$/i', $rel) === 1) {
            $webp = (string) preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $rel);
            if (is_file($this->projectDir.'/public/'.$webp)) {
                return $webp;
            }
        }

        return null;
    }
}
