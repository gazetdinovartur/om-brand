<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Removes uploaded files from public and private storage, including WebP variants.
 */
final class UploadFileRemover
{
    public function __construct(
        #[Autowire('%app.uploads_directory%')]
        private readonly string $publicUploadsDirectory,
        #[Autowire('%app.private_uploads_directory%')]
        private readonly string $privateUploadsDirectory,
    ) {
    }

    public function deleteAvatar(?string $basename): void
    {
        $this->deletePublicImage('avatars', $basename);
    }

    public function deleteCaseCover(?string $basename): void
    {
        $this->deletePublicImage('cases', $basename);
    }

    public function deleteCaseGallery(?string $basename): void
    {
        $this->deletePublicImage('cases/gallery', $basename);
    }

    public function deleteCaseAudio(?string $basename): void
    {
        $this->deletePublicFile('cases/audio', $basename);
    }

    public function deleteChronicleCover(?string $basename): void
    {
        $this->deletePublicImage('chronicle/covers', $basename);
    }

    public function deleteChronicleInline(?string $basename): void
    {
        $this->deletePublicImage('chronicle/inline', $basename);
    }

    public function deleteChronicleGallery(?string $basename): void
    {
        $this->deletePublicImage('chronicle/gallery', $basename);
    }

    public function deleteChronicleMediaUrl(?string $url): void
    {
        if (null === $url || '' === $url) {
            return;
        }

        $url = str_replace('\\', '/', $url);
        if (str_starts_with($url, 'uploads/')) {
            $url = substr($url, strlen('uploads/'));
        }

        if (!str_starts_with($url, 'chronicle/audio/')) {
            return;
        }

        $this->deletePublicFile($url);
    }

    public function deleteInquiryAttachment(?string $relativePath): void
    {
        $this->deletePrivateFile($relativePath);
    }

    public function deletePublicImage(string $directory, ?string $basename): void
    {
        $basename = $this->normalizeBasename($basename);
        if (null === $basename) {
            return;
        }

        $dir = trim(str_replace('\\', '/', $directory), '/');
        $stem = preg_replace('/\.[^.]+$/', '', $basename);
        if (!\is_string($stem) || '' === $stem) {
            return;
        }

        $candidates = [
            $dir.'/'.$basename,
            $dir.'/'.$stem.'.webp',
            $dir.'/'.$stem.'.thumb.webp',
            $dir.'/'.$stem.'.medium.webp',
            $dir.'/'.$stem.'.jpg',
            $dir.'/'.$stem.'.jpeg',
            $dir.'/'.$stem.'.png',
            $dir.'/'.$stem.'.gif',
        ];

        $this->unlinkPublicCandidates($candidates);
    }

    public function deletePublicFile(string $relativePath, ?string $basename = null): void
    {
        if (null !== $basename) {
            $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
            $basename = $this->normalizeBasename($basename);
            if (null === $basename) {
                return;
            }
            $relativePath = '' !== $relativePath ? $relativePath.'/'.$basename : $basename;
        }

        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
        if ('' === $relativePath) {
            return;
        }

        if (str_starts_with($relativePath, 'uploads/')) {
            $relativePath = substr($relativePath, strlen('uploads/'));
        }

        $absolute = rtrim($this->publicUploadsDirectory, '/').'/'.$relativePath;
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    public function deletePrivateFile(?string $relativePath): void
    {
        $relativePath = trim(str_replace('\\', '/', (string) $relativePath), '/');
        if ('' === $relativePath) {
            return;
        }

        $absolute = rtrim($this->privateUploadsDirectory, '/').'/'.$relativePath;
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    /**
     * @param list<string> $candidates
     */
    private function unlinkPublicCandidates(array $candidates): void
    {
        $base = rtrim($this->publicUploadsDirectory, '/');
        foreach (array_unique($candidates) as $relative) {
            $relative = trim(str_replace('\\', '/', $relative), '/');
            if ('' === $relative) {
                continue;
            }
            $absolute = $base.'/'.$relative;
            if (is_file($absolute)) {
                @unlink($absolute);
            }
        }
    }

    private function normalizeBasename(?string $path): ?string
    {
        if (null === $path || '' === $path) {
            return null;
        }

        $path = str_replace('\\', '/', $path);

        return basename($path);
    }
}
