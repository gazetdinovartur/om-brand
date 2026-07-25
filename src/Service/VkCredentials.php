<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resolves VK credentials from env (supports legacy/typo key names).
 */
final class VkCredentials
{
    public const PENDING_MARKER = '__vk_crosspost_pending__';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%env(VK_APP_ID)%')]
        private readonly string $appIdEnv = '',
        #[Autowire('%env(VK_APP_SECRET)%')]
        private readonly string $appSecretEnv = '',
        #[Autowire('%env(VK_SECRET_KEY)%')]
        private readonly string $secretKeyEnv = '',
        #[Autowire('%env(VK_SERVICE_TOKEN)%')]
        private readonly string $serviceTokenEnv = '',
        #[Autowire('%env(VK_SERVICE_KEY)%')]
        private readonly string $serviceKeyEnv = '',
        #[Autowire('%env(VK_SERVISE_KEY)%')]
        private readonly string $serviseKeyEnv = '',
        #[Autowire('%env(VK_USER_ACCESS_TOKEN)%')]
        private readonly string $userTokenEnv = '',
        #[Autowire('%env(VK_OWNER_ID)%')]
        private readonly string $ownerIdEnv = '',
        #[Autowire('%env(VK_CROSSPOST_ENABLED)%')]
        private readonly string $enabledEnv = '0',
    ) {
    }

    public function isEnabled(): bool
    {
        return \in_array(strtolower(trim($this->enabledEnv)), ['1', 'true', 'yes', 'on'], true);
    }

    public function hasUserToken(): bool
    {
        return '' !== $this->userAccessToken();
    }

    public function appId(): string
    {
        return trim($this->appIdEnv);
    }

    public function appSecret(): string
    {
        $secret = trim($this->appSecretEnv);
        if ('' !== $secret) {
            return $secret;
        }

        return trim($this->secretKeyEnv);
    }

    public function serviceToken(): string
    {
        foreach ([$this->serviceTokenEnv, $this->serviceKeyEnv, $this->serviseKeyEnv] as $candidate) {
            $token = trim($candidate);
            if ('' !== $token) {
                return $token;
            }
        }

        // Cyrillic «С» typo in key name (VK_SERVIСE_KEY).
        foreach (array_merge($_ENV, $_SERVER) as $key => $value) {
            if (!\is_string($key) || !\is_string($value) || '' === $value) {
                continue;
            }
            if (preg_match('/^VK_SERVI.E_KEY$/u', $key)) {
                return trim($value);
            }
        }

        return '';
    }

    public function userAccessToken(): string
    {
        $fromEnv = trim($this->userTokenEnv);
        if ('' !== $fromEnv) {
            return $fromEnv;
        }

        $path = $this->tokenFilePath();
        if (is_file($path)) {
            $raw = trim((string) file_get_contents($path));
            if ('' !== $raw) {
                return $raw;
            }
        }

        return '';
    }

    public function ownerId(): ?int
    {
        $raw = trim($this->ownerIdEnv);
        if ('' === $raw || !ctype_digit(ltrim($raw, '-'))) {
            return null;
        }

        return (int) $raw;
    }

    public function storeUserAccessToken(string $token): void
    {
        $token = trim($token);
        if ('' === $token) {
            throw new \InvalidArgumentException('Empty VK access token.');
        }

        $dir = \dirname($this->tokenFilePath());
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create VK token directory.');
        }

        if (false === file_put_contents($this->tokenFilePath(), $token."\n", LOCK_EX)) {
            throw new \RuntimeException('Cannot write VK token file.');
        }
        @chmod($this->tokenFilePath(), 0600);
    }

    public function tokenFilePath(): string
    {
        return $this->projectDir.'/var/vk/user_access_token';
    }

    public function canCrosspost(): bool
    {
        return $this->isEnabled() && $this->hasUserToken();
    }
}
