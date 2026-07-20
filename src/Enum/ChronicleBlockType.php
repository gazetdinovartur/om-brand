<?php

namespace App\Enum;

enum ChronicleBlockType: string
{
    case Paragraph = 'paragraph';
    case Heading = 'heading';
    case Image = 'image';
    case Gallery = 'gallery';
    case Quote = 'quote';
    case Audio = 'audio';
    case Video = 'video';
    case Divider = 'divider';
    case Callout = 'callout';

    public function label(): string
    {
        return match ($this) {
            self::Paragraph => 'Абзац',
            self::Heading => 'Заголовок',
            self::Image => 'Картинка',
            self::Gallery => 'Галерея',
            self::Quote => 'Цитата',
            self::Audio => 'Аудио (OmPlayer)',
            self::Video => 'Видео',
            self::Divider => 'Разделитель',
            self::Callout => 'Врезка',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Paragraph => '¶',
            self::Heading => 'H',
            self::Image => '🖼',
            self::Gallery => '▦',
            self::Quote => '❝',
            self::Audio => '♫',
            self::Video => '▶',
            self::Divider => '—',
            self::Callout => '💬',
        };
    }
}
