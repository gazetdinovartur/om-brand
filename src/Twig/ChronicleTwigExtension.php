<?php

namespace App\Twig;

use App\Service\ChronicleDisplayDeduper;
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
        private readonly ChronicleDisplayDeduper $deduper,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('chronicle_markdown', $this->markdown->toHtml(...), ['is_safe' => ['html']]),
            new TwigFilter('chronicle_date', $this->formatDate(...)),
        ];
    }

    /** @var array<int, string> */
    private const RU_MONTHS_GENITIVE = [
        1 => 'января',
        2 => 'февраля',
        3 => 'марта',
        4 => 'апреля',
        5 => 'мая',
        6 => 'июня',
        7 => 'июля',
        8 => 'августа',
        9 => 'сентября',
        10 => 'октября',
        11 => 'ноября',
        12 => 'декабря',
    ];

    /** @var array<int, string> */
    private const RU_MONTHS_SHORT = [
        1 => 'янв.',
        2 => 'февр.',
        3 => 'мар.',
        4 => 'апр.',
        5 => 'мая',
        6 => 'июн.',
        7 => 'июл.',
        8 => 'авг.',
        9 => 'сент.',
        10 => 'окт.',
        11 => 'нояб.',
        12 => 'дек.',
    ];

    /**
     * @param 'full'|'short' $style full = «15 июля 2026», short = «20 июл. 2026»
     */
    public function formatDate(?\DateTimeInterface $date, string $style = 'full'): string
    {
        if (null === $date) {
            return '';
        }

        $pattern = 'short' === $style ? 'd MMM y' : 'd MMMM y';

        if (class_exists(\IntlDateFormatter::class)) {
            $formatter = new \IntlDateFormatter(
                'ru_RU',
                \IntlDateFormatter::NONE,
                \IntlDateFormatter::NONE,
                $date->getTimezone()->getName(),
                \IntlDateFormatter::GREGORIAN,
                $pattern,
            );
            $formatted = $formatter->format($date);

            return \is_string($formatted) ? $formatted : '';
        }

        $day = (int) $date->format('j');
        $month = (int) $date->format('n');
        $year = $date->format('Y');
        $monthName = 'short' === $style
            ? (self::RU_MONTHS_SHORT[$month] ?? '')
            : (self::RU_MONTHS_GENITIVE[$month] ?? '');

        return trim(sprintf('%d %s %s', $day, $monthName, $year));
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('chronicle_video_embed', $this->media->videoEmbed(...)),
            new TwigFunction('chronicle_om_player_api', fn (): string => $this->media->omPlayerApiBase()),
            new TwigFunction('chronicle_show_hero_lede', $this->deduper->showHeroLede(...)),
            new TwigFunction('chronicle_show_card_lede', $this->deduper->showCardLede(...)),
            new TwigFunction('chronicle_blocks_for_display', $this->deduper->blocksForDisplay(...)),
        ];
    }
}
