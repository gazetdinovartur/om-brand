<?php

namespace App\Service;

use App\Entity\CaseStudy;

/**
 * Builds embeddable media payloads for case study presentation blocks.
 */
final class CaseMediaEmbedFactory
{
    public function __construct(
        private readonly string $omPlayerScriptUrl,
        private readonly string $omPlayerApiBase,
        private readonly string $omPlayerTheme = 'light',
    ) {
    }

    /**
     * @return array{provider: string, embedUrl: string, title: string}|null
     */
    public function videoEmbed(CaseStudy $case): ?array
    {
        $url = trim((string) $case->getVideoUrl());
        if ('' === $url) {
            return null;
        }

        $title = $case->getVideoTitle() ?: $case->getTitle();

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

    /**
     * @return array{
     *     mode: 'om-player'|'file'|'url',
     *     title: string,
     *     trackSlug?: string,
     *     src?: string,
     *     scriptUrl?: string,
     *     apiBase?: string,
     *     theme?: string
     * }|null
     */
    public function audioEmbed(CaseStudy $case): ?array
    {
        $title = $case->getAudioTitle() ?: ('Аудио: '.$case->getTitle());

        $slug = trim((string) $case->getOmTrackSlug());
        if ('' !== $slug) {
            return [
                'mode' => 'om-player',
                'title' => $title,
                'trackSlug' => $slug,
                'scriptUrl' => $this->omPlayerScriptUrl,
                'apiBase' => $this->omPlayerApiBase,
                'theme' => $this->omPlayerTheme,
            ];
        }

        $path = trim((string) $case->getAudioPath());
        if ('' !== $path) {
            $relative = str_contains($path, '/') ? ltrim($path, '/') : 'cases/audio/'.$path;

            return [
                'mode' => 'file',
                'title' => $title,
                'src' => 'uploads/'.$relative,
            ];
        }

        $url = trim((string) $case->getAudioUrl());
        if ('' !== $url) {
            return [
                'mode' => 'url',
                'title' => $title,
                'src' => $url,
            ];
        }

        return null;
    }

    public function omPlayerScriptUrl(): string
    {
        return $this->omPlayerScriptUrl;
    }
}
