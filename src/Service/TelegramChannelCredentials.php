<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Telegram channel outbound (separate from inquiry notifications).
 */
final class TelegramChannelCredentials
{
    public const PENDING_MARKER = '__tg_crosspost_pending__';

    public function __construct(
        #[Autowire('%env(TELEGRAM_BOT_TOKEN)%')]
        private readonly string $botTokenEnv = '',
        #[Autowire('%env(TELEGRAM_CHANNEL_ID)%')]
        private readonly string $channelIdEnv = '',
        #[Autowire('%env(TELEGRAM_CHANNEL_ENABLED)%')]
        private readonly string $enabledEnv = '0',
    ) {
    }

    public function isEnabled(): bool
    {
        if (\in_array(strtolower(trim($this->enabledEnv)), ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        // Auto-enable when channel id is configured (env already set by operator).
        return '' !== $this->channelId() && '' !== $this->botToken();
    }

    public function botToken(): string
    {
        return trim($this->botTokenEnv);
    }

    public function channelId(): string
    {
        return trim($this->channelIdEnv);
    }

    public function canCrosspost(): bool
    {
        return $this->isEnabled() && '' !== $this->botToken() && '' !== $this->channelId();
    }
}
