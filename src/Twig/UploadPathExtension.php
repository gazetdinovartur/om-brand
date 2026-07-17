<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class UploadPathExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('upload_path', $this->resolve(...)),
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
}
