<?php

declare(strict_types=1);

namespace App;

/**
 * Wall-clock timezone for the site (Екатеринбург).
 * Absolute instants stay correct; display and editorial dates use this zone.
 */
final class AppTimezone
{
    public const NAME = 'Asia/Yekaterinburg';

    public static function zone(): \DateTimeZone
    {
        return new \DateTimeZone(self::NAME);
    }

    public static function localize(\DateTimeInterface $date): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($date)->setTimezone(self::zone());
    }
}
