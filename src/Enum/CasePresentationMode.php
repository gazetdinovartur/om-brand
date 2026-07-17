<?php

namespace App\Enum;

enum CasePresentationMode: string
{
    case None = 'none';
    case Video = 'video';
    case Audio = 'audio';
    case VideoAudio = 'video_audio';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Без презентации',
            self::Video => 'Только видео',
            self::Audio => 'Только голос / аудио',
            self::VideoAudio => 'Видео и голос',
        };
    }

    public function wantsVideo(): bool
    {
        return self::Video === $this || self::VideoAudio === $this;
    }

    public function wantsAudio(): bool
    {
        return self::Audio === $this || self::VideoAudio === $this;
    }
}
