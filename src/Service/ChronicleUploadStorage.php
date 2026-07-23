<?php

namespace App\Service;

use App\Entity\ChronicleBlock;
use App\Entity\ChronicleBlockImage;
use App\Entity\ChronicleEntry;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final class ChronicleUploadStorage
{
    public function __construct(
        private readonly ImageOptimizer $imageOptimizer,
        private readonly string $projectDir,
    ) {
    }

    public function storeCover(UploadedFile $file): string
    {
        return $this->storeImage($file, 'chronicle/covers', 1600, 640);
    }

    public function storeInline(UploadedFile $file): string
    {
        return $this->storeImage($file, 'chronicle/inline', 1400, 640);
    }

    public function storeGallery(UploadedFile $file): string
    {
        return $this->storeImage($file, 'chronicle/gallery', 1400, 640);
    }

    public function optimizeEntryImages(ChronicleEntry $entry): void
    {
        $cover = $this->optimizePath($entry->getCoverImagePath(), 'chronicle/covers', 1600, 640);
        if (null !== $cover) {
            $entry->setCoverImagePath($cover);
        }

        $og = $this->optimizePath($entry->getOgImagePath(), 'chronicle/covers', 1200, null);
        if (null !== $og) {
            $entry->setOgImagePath($og);
        }

        foreach ($entry->getBlocks() as $block) {
            if (null !== $block->getImagePath()) {
                $inline = $this->optimizePath($block->getImagePath(), 'chronicle/inline', 1400, 640);
                if (null !== $inline) {
                    $block->setImagePath($inline);
                }
            }
            foreach ($block->getImages() as $image) {
                $gallery = $this->optimizePath($image->getImagePath(), 'chronicle/gallery', 1400, 640);
                if (null !== $gallery) {
                    $image->setImagePath($gallery);
                }
            }
        }
    }

    private function storeImage(UploadedFile $file, string $subdir, int $maxWidth, ?int $thumbWidth): string
    {
        $filename = Uuid::v7()->toRfc4122().'.'.$file->guessExtension();
        $dir = $this->projectDir.'/public/uploads/'.$subdir;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $file->move($dir, $filename);
        $result = $this->imageOptimizer->optimizeToWebp(
            $subdir.'/'.$filename,
            maxWidth: $maxWidth,
            thumbWidth: $thumbWidth,
        );

        return basename($result['path']);
    }

    private function optimizePath(?string $path, string $subdir, int $maxWidth, ?int $thumbWidth): ?string
    {
        if (null === $path || '' === $path) {
            return null;
        }

        $filename = basename(str_replace('\\', '/', $path));
        if (str_ends_with(strtolower($filename), '.webp')) {
            return $filename;
        }

        $relative = $subdir.'/'.$filename;
        if (!is_file($this->projectDir.'/public/uploads/'.$relative)) {
            $webp = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $filename);
            if (\is_string($webp) && is_file($this->projectDir.'/public/uploads/'.$subdir.'/'.$webp)) {
                return $webp;
            }

            return $filename;
        }

        $result = $this->imageOptimizer->optimizeToWebp($relative, maxWidth: $maxWidth, thumbWidth: $thumbWidth);

        return basename($result['path']);
    }
}
