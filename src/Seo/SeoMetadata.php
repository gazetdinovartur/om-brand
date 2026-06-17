<?php

namespace App\Seo;

final readonly class SeoMetadata
{
    /**
     * @param array<string, mixed>|null $jsonLd
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $canonicalUrl,
        public string $robots,
        public string $ogType,
        public ?string $ogImageUrl,
        public ?array $jsonLd = null,
        public ?string $keywords = null,
    ) {
    }

    public function jsonLdScript(): ?string
    {
        if (null === $this->jsonLd) {
            return null;
        }

        return json_encode(
            $this->jsonLd,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }
}
