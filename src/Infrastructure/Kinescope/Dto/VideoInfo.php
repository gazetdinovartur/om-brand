<?php

declare(strict_types=1);

namespace App\Infrastructure\Kinescope\Dto;

final readonly class VideoInfo
{
    public function __construct(
        public string $id,
        public string $title = '',
        public string $status = '',
        public ?string $posterUrl = null,
        public ?float $duration = null,
    ) {
    }

    public function isReady(): bool
    {
        return \in_array($this->status, ['done', 'ready', 'published'], true)
            || ('' !== $this->status && !\in_array($this->status, ['processing', 'uploading', 'error', 'aborted'], true) && null !== $this->posterUrl);
    }
}
