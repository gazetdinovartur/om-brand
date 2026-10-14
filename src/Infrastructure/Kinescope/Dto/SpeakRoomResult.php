<?php

declare(strict_types=1);

namespace App\Infrastructure\Kinescope\Dto;

final readonly class SpeakRoomResult
{
    public function __construct(
        public string $roomId,
        public string $code,
        public string $link,
    ) {
    }
}
