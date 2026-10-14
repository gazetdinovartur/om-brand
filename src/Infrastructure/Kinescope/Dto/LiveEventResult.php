<?php

declare(strict_types=1);

namespace App\Infrastructure\Kinescope\Dto;

final readonly class LiveEventResult
{
    public function __construct(
        public string $eventId,
        public ?string $ingestUrl = null,
        public ?string $streamKey = null,
        public ?string $playLink = null,
    ) {
    }
}
