<?php

namespace App\Service;

use App\Entity\ChronicleBlock;
use App\Enum\ChronicleBlockType;
use App\Enum\ChronicleStatus;

final class ChronicleMediaEmbedFactory
{
    public function __construct(
        private readonly string $omPlayerScriptUrl,
        private readonly string $omPlayerApiBase,
        private readonly string $omPlayerTheme = 'light',
    ) {
    }

    /**
     * @return array{mode: 'om-player', title: string, trackSlug: string, scriptUrl: string, apiBase: string, theme: string}|null
     */
    public function audioEmbed(ChronicleBlock $block): ?array
    {
        if (ChronicleBlockType::Audio !== $block->getType()) {
            return null;
        }

        $slug = trim((string) $block->getOmTrackSlug());
        if ('' === $slug) {
            return null;
        }

        $title = $block->getCaption() ?: 'Аудио';

        return [
            'mode' => 'om-player',
            'title' => $title,
            'trackSlug' => $slug,
            'scriptUrl' => $this->omPlayerScriptUrl,
            'apiBase' => $this->omPlayerApiBase,
            'theme' => $this->omPlayerTheme,
        ];
    }

    /**
     * @return array{provider: string, embedUrl: string, title: string}|null
     */
    public function videoEmbed(ChronicleBlock $block): ?array
    {
        if (ChronicleBlockType::Video !== $block->getType()) {
            return null;
        }

        $url = trim((string) $block->getVideoUrl());
        if ('' === $url) {
            return null;
        }

        $title = $block->getVideoTitle() ?: 'Видео';

        if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{6,})~', $url, $m)) {
            return [
                'provider' => 'youtube',
                'embedUrl' => 'https://www.youtube-nocookie.com/embed/'.$m[1],
                'title' => $title,
            ];
        }

        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
            return [
                'provider' => 'vimeo',
                'embedUrl' => 'https://player.vimeo.com/video/'.$m[1],
                'title' => $title,
            ];
        }

        if (preg_match('~rutube\.ru/(?:video|play/embed)/([A-Za-z0-9]+)~', $url, $m)) {
            return [
                'provider' => 'rutube',
                'embedUrl' => 'https://rutube.ru/play/embed/'.$m[1],
                'title' => $title,
            ];
        }

        return [
            'provider' => 'link',
            'embedUrl' => $url,
            'title' => $title,
        ];
    }

    public function omPlayerScriptUrl(): string
    {
        return $this->omPlayerScriptUrl;
    }

    public function omPlayerApiBase(): string
    {
        return $this->omPlayerApiBase;
    }

    public function entryHasOmPlayer(ChronicleBlock ...$blocks): bool
    {
        foreach ($blocks as $block) {
            if (ChronicleBlockType::Audio === $block->getType() && '' !== trim((string) $block->getOmTrackSlug())) {
                return true;
            }
        }

        return false;
    }
}
