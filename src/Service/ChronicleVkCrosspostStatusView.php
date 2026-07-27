<?php

namespace App\Service;

final readonly class ChronicleVkCrosspostStatusView
{
    public function __construct(
        public string $kind,
        public string $label,
        public ?string $hint = null,
        public ?string $wallUrl = null,
    ) {
    }

    /**
     * @return array{kind: string, label: string, hint: ?string, wallUrl: ?string}
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'label' => $this->label,
            'hint' => $this->hint,
            'wallUrl' => $this->wallUrl,
        ];
    }
}
