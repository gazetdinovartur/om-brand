<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Uid\Uuid;

class InquiryAttachmentStorage
{
    private const MAX_SIZE = 5 * 1024 * 1024;

    /** @var list<string> */
    private const ALLOWED_MIME = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'text/plain',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function __construct(
        #[Autowire('%app.uploads_directory%')]
        private readonly string $uploadsDirectory,
        private readonly SluggerInterface $slugger,
    ) {
    }

    /**
     * @return array{path: string, originalName: string, mimeType: string}
     */
    public function store(UploadedFile $file): array
    {
        if ($file->getSize() > self::MAX_SIZE) {
            throw new FileException('Файл слишком большой. Максимум 5 МБ.');
        }

        $mimeType = $file->getMimeType() ?? 'application/octet-stream';
        if (!in_array($mimeType, self::ALLOWED_MIME, true)) {
            throw new FileException('Можно прикрепить фото или текстовый документ (jpg, png, pdf, txt, doc).');
        }

        $originalName = $file->getClientOriginalName();
        $safeName = (string) $this->slugger->slug(pathinfo($originalName, PATHINFO_FILENAME));
        $extension = $file->guessExtension() ?? 'bin';
        $filename = sprintf('%s-%s.%s', Uuid::v7()->toRfc4122(), $safeName, $extension);

        $targetDir = rtrim($this->uploadsDirectory, '/').'/inquiries';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new FileException('Не удалось создать каталог для загрузок.');
        }

        $file->move($targetDir, $filename);

        return [
            'path' => 'inquiries/'.$filename,
            'originalName' => $originalName,
            'mimeType' => $mimeType,
        ];
    }

    public function getAbsolutePath(string $relativePath): string
    {
        return rtrim($this->uploadsDirectory, '/').'/'.$relativePath;
    }
}
