<?php

namespace App\Validation;

use App\Enum\ContactType;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validation;

final class ContactValueValidator
{
    public static function validate(string $contact, ContactType $type): ?string
    {
        $contact = trim($contact);
        if ('' === $contact) {
            return 'Укажите, как с вами связаться';
        }

        return match ($type) {
            ContactType::Phone => self::validatePhone($contact),
            ContactType::Email => self::validateEmail($contact),
            ContactType::Telegram => self::validateTelegram($contact),
            ContactType::Vk => self::validateVk($contact),
        };
    }

    public static function normalize(string $contact, ContactType $type): string
    {
        $contact = trim($contact);

        return match ($type) {
            ContactType::Phone => self::formatPhone($contact),
            ContactType::Email => mb_strtolower($contact),
            ContactType::Telegram => self::normalizeTelegram($contact),
            ContactType::Vk => self::normalizeVk($contact),
        };
    }

    private static function validatePhone(string $contact): ?string
    {
        if (null === self::extractPhoneDigits($contact)) {
            return 'Введите номер в формате +7 (999) 123-45-67';
        }

        return null;
    }

    private static function validateEmail(string $contact): ?string
    {
        $violations = Validation::createValidator()->validate($contact, [new Email(mode: Email::VALIDATION_MODE_HTML5)]);

        return $violations->count() > 0 ? 'Введите корректный email, например name@example.com' : null;
    }

    private static function validateTelegram(string $contact): ?string
    {
        if (1 === preg_match('#^https?://(t\.me|telegram\.me)/([A-Za-z0-9_]{5,32})/?$#i', $contact)) {
            return null;
        }

        if (1 === preg_match('/^\+?\d{10,15}$/', preg_replace('/[\s()-]/', '', $contact))) {
            return null;
        }

        $username = ltrim($contact, '@');
        if (1 === preg_match('/^[A-Za-z0-9_]{5,32}$/', $username)) {
            return null;
        }

        return 'Укажите @username, ссылку t.me/username или номер телефона';
    }

    private static function validateVk(string $contact): ?string
    {
        if (1 === preg_match('#^https?://(vk\.com|vk\.me)/.+$#i', $contact)) {
            return null;
        }

        if (1 === preg_match('/^id\d+$/i', $contact)) {
            return null;
        }

        if (1 === preg_match('/^\d{5,15}$/', $contact)) {
            return null;
        }

        $username = ltrim($contact, '@');
        if (1 === preg_match('/^[A-Za-z0-9_.]{3,32}$/', $username)) {
            return null;
        }

        return 'Укажите ссылку vk.com/..., id123456 или короткое имя';
    }

    /**
     * @return list<string>|null
     */
    private static function extractPhoneDigits(string $contact): ?array
    {
        $digits = preg_replace('/\D+/', '', $contact) ?? '';
        if ('' === $digits) {
            return null;
        }

        if (str_starts_with($digits, '8') && 11 === strlen($digits)) {
            $digits = '7'.substr($digits, 1);
        }

        if (10 === strlen($digits) && str_starts_with($digits, '9')) {
            $digits = '7'.$digits;
        }

        if (11 !== strlen($digits) || !str_starts_with($digits, '7')) {
            return null;
        }

        return str_split($digits);
    }

    private static function formatPhone(string $contact): string
    {
        $digits = self::extractPhoneDigits($contact);
        if (null === $digits) {
            return trim($contact);
        }

        return sprintf(
            '+7 (%s) %s-%s-%s',
            implode('', array_slice($digits, 1, 3)),
            implode('', array_slice($digits, 4, 3)),
            implode('', array_slice($digits, 7, 2)),
            implode('', array_slice($digits, 9, 2)),
        );
    }

    private static function normalizeTelegram(string $contact): string
    {
        if (preg_match('#^https?://(t\.me|telegram\.me)/([A-Za-z0-9_]{5,32})/?$#i', $contact, $matches)) {
            return '@'.strtolower($matches[2]);
        }

        if (str_starts_with($contact, '@')) {
            return $contact;
        }

        if (1 === preg_match('/^[A-Za-z0-9_]{5,32}$/', $contact)) {
            return '@'.$contact;
        }

        return $contact;
    }

    private static function normalizeVk(string $contact): string
    {
        if (preg_match('#^https?://(vk\.com|vk\.me)/(.+)$#i', $contact, $matches)) {
            return rtrim($matches[2], '/');
        }

        return ltrim($contact, '@');
    }
}
