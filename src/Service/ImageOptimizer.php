<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

final class ImageOptimizer
{
    public function __construct(
        #[Autowire('%app.uploads_directory%')]
        private readonly string $publicUploadsDirectory,
    ) {
    }

    /**
     * Converts a public upload to WebP, optionally generates a smaller variant.
     *
     * @return array{path: string, srcset: ?string}
     */
    public function optimizeToWebp(string $relativePath, int $maxWidth = 800, ?int $thumbWidth = null): array
    {
        if (!extension_loaded('gd')) {
            return ['path' => $relativePath, 'srcset' => null];
        }

        $absolute = rtrim($this->publicUploadsDirectory, '/').'/'.$relativePath;
        if (!is_file($absolute)) {
            return ['path' => $relativePath, 'srcset' => null];
        }

        $mime = mime_content_type($absolute) ?: '';
        if (!str_starts_with($mime, 'image/') || str_contains($mime, 'svg')) {
            return ['path' => $relativePath, 'srcset' => null];
        }

        $source = $this->createImage($absolute, $mime);
        if (null === $source) {
            return ['path' => $relativePath, 'srcset' => null];
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $targetWidth = min($width, $maxWidth);
        $targetHeight = (int) round($height * ($targetWidth / $width));

        $main = $this->resize($source, $width, $height, $targetWidth, $targetHeight);
        $mainPath = $this->replaceExtension($relativePath, 'webp');
        $this->saveWebp($main, rtrim($this->publicUploadsDirectory, '/').'/'.$mainPath);
        imagedestroy($main);

        $srcset = null;
        if (null !== $thumbWidth && $width > $thumbWidth) {
            $thumbHeight = (int) round($height * ($thumbWidth / $width));
            $thumb = $this->resize($source, $width, $height, $thumbWidth, $thumbHeight);
            $thumbPath = $this->replaceExtension($relativePath, 'thumb.webp');
            $this->saveWebp($thumb, rtrim($this->publicUploadsDirectory, '/').'/'.$thumbPath);
            imagedestroy($thumb);
            $srcset = sprintf('%s %dw', $thumbPath, $thumbWidth);
        }

        imagedestroy($source);
        @unlink($absolute);

        return ['path' => $mainPath, 'srcset' => $srcset];
    }

    /**
     * @param \GdImage|resource $source
     *
     * @return \GdImage|resource
     */
    private function resize($source, int $width, int $height, int $targetWidth, int $targetHeight)
    {
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $canvas;
    }

    private function saveWebp($image, string $absolutePath): void
    {
        $dir = \dirname($absolutePath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new FileException('Не удалось создать каталог для изображения.');
        }

        if (!imagewebp($image, $absolutePath, 82)) {
            throw new FileException('Не удалось сохранить изображение.');
        }
    }

    /**
     * @return \GdImage|resource|null
     */
    private function createImage(string $absolutePath, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($absolutePath) ?: null,
            'image/png' => @imagecreatefrompng($absolutePath) ?: null,
            'image/webp' => @imagecreatefromwebp($absolutePath) ?: null,
            'image/gif' => @imagecreatefromgif($absolutePath) ?: null,
            default => null,
        };
    }

    private function replaceExtension(string $path, string $extension): string
    {
        return preg_replace('/\.[^.]+$/', '.'.$extension, $path) ?? ($path.'.'.$extension);
    }
}
