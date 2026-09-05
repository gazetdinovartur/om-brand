<?php

namespace App\Service;

use App\Entity\ChronicleEntry;

final readonly class ChronicleTelegramCrosspostStatusView
{
    public function __construct(
        public string $kind,
        public string $label,
        public ?string $hint = null,
        public ?string $channelUrl = null,
    ) {
    }

    /**
     * @return array{kind: string, label: string, hint: ?string, channelUrl: ?string}
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'label' => $this->label,
            'hint' => $this->hint,
            'channelUrl' => $this->channelUrl,
        ];
    }
}
