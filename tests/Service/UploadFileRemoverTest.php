<?php

namespace App\Tests\Service;

use App\Service\UploadFileRemover;
use PHPUnit\Framework\TestCase;

final class UploadFileRemoverTest extends TestCase
{
    private string $publicDir;

    private string $privateDir;

    protected function setUp(): void
    {
        $this->publicDir = sys_get_temp_dir().'/upload-remover-public-'.bin2hex(random_bytes(4));
        $this->privateDir = sys_get_temp_dir().'/upload-remover-private-'.bin2hex(random_bytes(4));
        mkdir($this->publicDir.'/cases/gallery', 0775, true);
        mkdir($this->privateDir.'/inquiries', 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->publicDir);
        $this->removeTree($this->privateDir);
    }

    public function testDeletePublicImageRemovesWebpVariants(): void
    {
        $this->touch('cases/gallery/photo.webp');
        $this->touch('cases/gallery/photo.thumb.webp');
        $this->touch('cases/gallery/photo.medium.webp');

        $this->remover()->deleteCaseGallery('photo.webp');

        self::assertFileDoesNotExist($this->publicDir.'/cases/gallery/photo.webp');
        self::assertFileDoesNotExist($this->publicDir.'/cases/gallery/photo.thumb.webp');
        self::assertFileDoesNotExist($this->publicDir.'/cases/gallery/photo.medium.webp');
    }

    public function testDeleteCaseAudioRemovesSingleFile(): void
    {
        mkdir($this->publicDir.'/cases/audio', 0775, true);
        $this->touch('cases/audio/track.mp3');

        $this->remover()->deleteCaseAudio('track.mp3');

        self::assertFileDoesNotExist($this->publicDir.'/cases/audio/track.mp3');
    }

    public function testDeleteChronicleMediaUrlIgnoresExternalLinks(): void
    {
        mkdir($this->publicDir.'/chronicle/audio', 0775, true);
        $this->touch('chronicle/audio/local.mp3');

        $this->remover()->deleteChronicleMediaUrl('https://example.com/audio.mp3');
        self::assertFileExists($this->publicDir.'/chronicle/audio/local.mp3');

        $this->remover()->deleteChronicleMediaUrl('chronicle/audio/local.mp3');
        self::assertFileDoesNotExist($this->publicDir.'/chronicle/audio/local.mp3');
    }

    public function testDeleteInquiryAttachmentUsesPrivateStorage(): void
    {
        $this->touchPrivate('inquiries/file.pdf');

        $this->remover()->deleteInquiryAttachment('inquiries/file.pdf');

        self::assertFileDoesNotExist($this->privateDir.'/inquiries/file.pdf');
    }

    private function remover(): UploadFileRemover
    {
        return new UploadFileRemover($this->publicDir, $this->privateDir);
    }

    private function touch(string $relative): void
    {
        $absolute = $this->publicDir.'/'.$relative;
        $dir = \dirname($absolute);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($absolute, 'x');
    }

    private function touchPrivate(string $relative): void
    {
        file_put_contents($this->privateDir.'/'.$relative, 'x');
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if (false === $items) {
            return;
        }

        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }
            $path = $dir.'/'.$item;
            if (is_dir($path)) {
                $this->removeTree($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
