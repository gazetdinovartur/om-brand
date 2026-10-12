<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Проверка данных Telegram Login Widget.
 *
 * @see https://core.telegram.org/widgets/login#checking-authorization
 */
final class TelegramLoginVerifier
{
    private const MAX_AUTH_AGE_SECONDS = 86400;

    public function __construct(
        #[Autowire('%env(TELEGRAM_BOT_TOKEN)%')]
        private readonly string $botToken = '',
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->botToken);
    }

    public function botUsername(): ?string
    {
        // Username берётся из env отдельно; токен сам по себе username не содержит.
        return null;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{ok: true, user: array{id: int|string, username?: string, first_name?: string, last_name?: string, photo_url?: string}}|array{ok: false, message: string}
     */
    public function verify(array $payload): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'Вход через Telegram временно недоступен.'];
        }

        $hash = (string) ($payload['hash'] ?? '');
        if ('' === $hash) {
            return ['ok' => false, 'message' => 'Нет подписи Telegram.'];
        }

        $authDate = (int) ($payload['auth_date'] ?? 0);
        if ($authDate < 1 || (time() - $authDate) > self::MAX_AUTH_AGE_SECONDS) {
            return ['ok' => false, 'message' => 'Данные Telegram устарели. Попробуйте ещё раз.'];
        }

        $check = $payload;
        unset($check['hash']);
        ksort($check);
        $parts = [];
        foreach ($check as $key => $value) {
            if (null === $value || '' === $value) {
                continue;
            }
            $parts[] = $key.'='.$value;
        }
        $dataCheckString = implode("\n", $parts);
        $secretKey = hash('sha256', $this->botToken, true);
        $calculated = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (!hash_equals($calculated, $hash)) {
            return ['ok' => false, 'message' => 'Подпись Telegram не совпала.'];
        }

        if (!isset($payload['id'])) {
            return ['ok' => false, 'message' => 'В ответе Telegram нет id пользователя.'];
        }

        return [
            'ok' => true,
            'user' => [
                'id' => $payload['id'],
                'username' => isset($payload['username']) ? (string) $payload['username'] : null,
                'first_name' => isset($payload['first_name']) ? (string) $payload['first_name'] : null,
                'last_name' => isset($payload['last_name']) ? (string) $payload['last_name'] : null,
                'photo_url' => isset($payload['photo_url']) ? (string) $payload['photo_url'] : null,
            ],
        ];
    }
}
