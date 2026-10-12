<?php

declare(strict_types=1);

namespace App\Library;

final class LibrarySourceReader
{
    private const MAX_SOURCE_BYTES = 10_000_000;

    public function read(string $path): LibrarySource
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \InvalidArgumentException(sprintf('Файл недоступен: %s', $path));
        }

        $extension = strtolower(pathinfo($path, \PATHINFO_EXTENSION));
        if ('zip' === $extension) {
            return $this->readZip($path);
        }

        if ('txt' !== $extension) {
            throw new \InvalidArgumentException('Поддерживаются только TXT и ZIP с одним TXT-файлом.');
        }

        $bytes = file_get_contents($path);
        if (false === $bytes) {
            throw new \RuntimeException(sprintf('Не удалось прочитать файл: %s', $path));
        }
        $this->assertSafeSize(strlen($bytes));

        return new LibrarySource(
            $this->decode($bytes),
            hash('sha256', $bytes),
            basename($path),
        );
    }

    private function readZip(string $path): LibrarySource
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
                if (!str_ends_with(strtolower($name), '.txt')) {
                    continue;
                }
                $size = (int) ($stat['size'] ?? 0);
                $this->assertSafeSize($size);
                $candidates[] = ['index' => $index, 'name' => $name, 'size' => $size];
            }

            if ([] === $candidates) {
                throw new \RuntimeException('В ZIP не найден TXT-файл.');
            }

            usort(
                $candidates,
                static fn (array $left, array $right): int => $right['size'] <=> $left['size'],
            );
            $selected = $candidates[0];
            $bytes = $zip->getFromIndex((int) $selected['index']);
            if (false === $bytes) {
                throw new \RuntimeException('Не удалось прочитать TXT из ZIP.');
            }
        } finally {
            $zip->close();
        }

        return new LibrarySource(
            $this->decode($bytes),
            hash('sha256', $bytes),
            (string) $selected['name'],
        );
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
            throw new \RuntimeException('Текстовый файл пуст.');
        }
        if ($size > self::MAX_SOURCE_BYTES) {
            throw new \RuntimeException('Текстовый файл превышает безопасный лимит 10 МБ.');
        }
    }
}
