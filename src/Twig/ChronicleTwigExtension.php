<?php

namespace App\Twig;

use App\Entity\ChronicleBlock;
use App\Service\ChronicleMarkdownRenderer;
use App\Service\ChronicleMediaEmbedFactory;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class ChronicleTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly ChronicleMarkdownRenderer $markdown,
        private readonly ChronicleMediaEmbedFactory $media,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('chronicle_markdown', $this->markdown->toHtml(...), ['is_safe' => ['html']]),
            new TwigFilter('chronicle_date', $this->formatDate(...)),
        ];
    }

    /**
     * @param 'full'|'short' $style full = «15 July 2026», short = «20 Jul 2026»
     */
    public function formatDate(?\DateTimeInterface $date, string $style = 'full'): string
    {
        if (null === $date) {
            return '';
        }

        $pattern = 'short' === $style ? 'd MMM y' : 'd MMMM y';

        if (class_exists(\IntlDateFormatter::class)) {
            $formatter = new \IntlDateFormatter(
                'en_GB',
                \IntlDateFormatter::NONE,
                \IntlDateFormatter::NONE,
                $date->getTimezone()->getName(),
                \IntlDateFormatter::GREGORIAN,
                $pattern,
            );
            $formatted = $formatter->format($date);

            return \is_string($formatted) ? $formatted : '';
        }

        return $date->format('short' === $style ? 'j M Y' : 'j F Y');
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('chronicle_video_embed', $this->media->videoEmbed(...)),
            new TwigFunction('chronicle_om_player_api', fn (): string => $this->media->omPlayerApiBase()),
        ];
    }
}
