<?php

declare(strict_types=1);

namespace App\Library;

final class LibrarySourceReader
{
    private const MAX_SOURCE_BYTES = 10_000_000;

    /** @var list<string> */
    private const DIRECT_EXTENSIONS = ['txt', 'md', 'markdown', 'fb2', 'epub'];

    /** @var list<string> */
    private const ZIP_INNER_EXTENSIONS = ['txt', 'md', 'markdown', 'fb2'];

    public function read(string $path, ?string $sourceName = null): LibrarySource
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \InvalidArgumentException(sprintf('Файл недоступен: %s', $path));
        }

        $extension = strtolower(pathinfo($path, \PATHINFO_EXTENSION));
        if ('zip' === $extension) {
            return $this->readZip($path, $sourceName);
        }

        if (!\in_array($extension, self::DIRECT_EXTENSIONS, true)) {
            throw new \InvalidArgumentException(
                'Поддерживаются TXT, MD, FB2, EPUB и ZIP с txt/md/fb2 внутри.',
            );
        }

        $bytes = file_get_contents($path);
        if (false === $bytes) {
            throw new \RuntimeException(sprintf('Не удалось прочитать файл: %s', $path));
        }
        $this->assertSafeSize(strlen($bytes));

        $format = $this->normalizeFormat($extension);
        // FB2/EPUB keep raw bytes: XML encoding is handled inside the format parser.
        $payload = \in_array($format, [LibrarySource::FORMAT_FB2, LibrarySource::FORMAT_EPUB], true)
            ? $bytes
            : $this->decode($bytes);

        return new LibrarySource(
            $payload,
            hash('sha256', $bytes),
            $this->resolveSourceName($sourceName, basename($path)),
            $format,
        );
    }

    private function readZip(string $path, ?string $sourceName): LibrarySource
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Для импорта ZIP требуется расширение PHP zip.');
        }

        $zip = new \ZipArchive();
        $result = $zip->open($path);
        if (true !== $result) {
            throw new \RuntimeException(sprintf('Не удалось открыть ZIP (%s).', (string) $result));
        }

        try {
            $candidates = [];
            for ($index = 0; $index < $zip->numFiles; ++$index) {
                $stat = $zip->statIndex($index);
                if (false === $stat) {
                    continue;
                }
                $name = (string) ($stat['name'] ?? '');
                $innerExt = strtolower(pathinfo($name, \PATHINFO_EXTENSION));
                if (!\in_array($innerExt, self::ZIP_INNER_EXTENSIONS, true)) {
                    continue;
                }
                $size = (int) ($stat['size'] ?? 0);
                $this->assertSafeSize($size);
                $candidates[] = [
                    'index' => $index,
                    'name' => $name,
                    'size' => $size,
                    'format' => $this->normalizeFormat($innerExt),
                ];
            }

            if ([] === $candidates) {
                throw new \RuntimeException('В ZIP не найден файл TXT, MD или FB2.');
            }

            usort(
                $candidates,
                static fn (array $left, array $right): int => $right['size'] <=> $left['size'],
            );
            $selected = $candidates[0];
            $bytes = $zip->getFromIndex((int) $selected['index']);
            if (false === $bytes) {
                throw new \RuntimeException('Не удалось прочитать файл из ZIP.');
            }
        } finally {
            $zip->close();
        }

        $format = (string) $selected['format'];
        $payload = LibrarySource::FORMAT_FB2 === $format
            ? $bytes
            : $this->decode($bytes);

        return new LibrarySource(
            $payload,
            hash('sha256', $bytes),
            $this->resolveSourceName($sourceName, (string) $selected['name']),
            $format,
        );
    }

    private function resolveSourceName(?string $preferred, string $fallback): string
    {
        $preferred = null !== $preferred ? trim($preferred) : '';

        return '' !== $preferred ? basename($preferred) : $fallback;
    }

    private function normalizeFormat(string $extension): string
    {
        return match ($extension) {
            'md', 'markdown' => LibrarySource::FORMAT_MD,
            'fb2' => LibrarySource::FORMAT_FB2,
            'epub' => LibrarySource::FORMAT_EPUB,
            default => LibrarySource::FORMAT_TXT,
        };
    }

    private function decode(string $bytes): string
    {
        if (1 === preg_match('//u', $bytes)) {
            $text = $bytes;
        } else {
            $converted = @iconv('Windows-1251', 'UTF-8', $bytes);
            if (false === $converted || 1 !== preg_match('//u', $converted)) {
                throw new \RuntimeException('Не удалось определить кодировку текста (ожидалась UTF-8 или Windows-1251).');
            }
            $text = $converted;
        }

        $text = preg_replace('/^\x{FEFF}/u', '', $text) ?? $text;

        return str_replace(["\r\n", "\r"], "\n", $text);
    }

    private function assertSafeSize(int $size): void
    {
        if ($size <= 0) {
            throw new \RuntimeException('Файл пуст.');
        }
        if ($size > self::MAX_SOURCE_BYTES) {
            throw new \RuntimeException('Файл превышает безопасный лимит 10 МБ.');
        }
    }
}
