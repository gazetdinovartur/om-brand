<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resolves VK credentials from env (supports legacy/typo key names).
 *
 * Crosspost target: VK_OWNER_ID (community preferred).
 * Inbound sync: VK_SYNC_OWNER_ID (personal wall), falls back to VK_OWNER_ID.
 * Community token for wall.post; user token for photos / wall.edit / wall.get.
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
        #[Autowire('%env(VK_COMMUNITY_TOKEN)%')]
        private readonly string $communityTokenEnv = '',
        #[Autowire('%env(VK_OWNER_ID)%')]
        private readonly string $ownerIdEnv = '',
        #[Autowire('%env(VK_SYNC_OWNER_ID)%')]
        private readonly string $syncOwnerIdEnv = '',
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

    public function hasCommunityToken(): bool
    {
        return '' !== $this->communityToken();
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

    public function communityToken(): string
    {
        return trim($this->communityTokenEnv);
    }

    public function ownerId(): ?int
    {
        return $this->parseOwnerId($this->ownerIdEnv);
    }

    /**
     * Raw VK_OWNER_ID (crosspost target: community id or screen name).
     */
    public function ownerIdRaw(): string
    {
        return trim($this->ownerIdEnv);
    }

    /**
     * Wall to pull into chronicle. Prefers VK_SYNC_OWNER_ID (personal), else VK_OWNER_ID.
     */
    public function syncOwnerId(): ?int
    {
        $parsed = $this->parseOwnerId($this->syncOwnerIdEnv);
        if (null !== $parsed) {
            return $parsed;
        }

        return $this->ownerId();
    }

    public function syncOwnerIdRaw(): string
    {
        $sync = trim($this->syncOwnerIdEnv);
        if ('' !== $sync) {
            return $sync;
        }

        return $this->ownerIdRaw();
    }

    private function parseOwnerId(string $rawEnv): ?int
    {
        $raw = trim($rawEnv);
        if ('' === $raw) {
            return null;
        }

        if (str_starts_with($raw, '@')) {
            $raw = substr($raw, 1);
        }

        if (1 === preg_match('/^-?\d+$/', $raw)) {
            return (int) $raw;
        }

        return null;
    }

    public function isCommunityTarget(): bool
    {
        $ownerId = $this->ownerId();
        if (null !== $ownerId) {
            return $ownerId < 0;
        }

        // Non-numeric VK_OWNER_ID is treated as a community screen name.
        return '' !== $this->ownerIdRaw();
    }

    /**
     * Token for wall.post (community token preferred for groups).
     */
    public function postingToken(): string
    {
        if ($this->isCommunityTarget() && $this->hasCommunityToken()) {
            return $this->communityToken();
        }

        return $this->userAccessToken();
    }

    /**
     * User token for methods that reject community auth (wall.get, photos, wall.edit, resolve).
     */
    public function userApiToken(): string
    {
        return $this->userAccessToken();
    }

    /**
     * @deprecated Use postingToken() or userApiToken() — community token must not fall back for user-only methods.
     */
    public function apiToken(): string
    {
        return $this->userApiToken() !== '' ? $this->userApiToken() : $this->communityToken();
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

    public function storeRefreshMeta(string $refreshToken, string $deviceId): void
    {
        $dir = \dirname($this->tokenFilePath());
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create VK token directory.');
        }

        $payload = json_encode([
            'refresh_token' => trim($refreshToken),
            'device_id' => trim($deviceId),
            'updated_at' => (new \DateTimeImmutable())->format('c'),
        ], \JSON_THROW_ON_ERROR);

        $path = $dir.'/refresh.json';
        if (false === file_put_contents($path, $payload."\n", LOCK_EX)) {
            throw new \RuntimeException('Cannot write VK refresh meta.');
        }
        @chmod($path, 0600);
    }

    public function tokenFilePath(): string
    {
        return $this->projectDir.'/var/vk/user_access_token';
    }

    public function canCrosspost(): bool
    {
        if (!$this->isEnabled() || '' === $this->ownerIdRaw()) {
            return false;
        }

        if ($this->isCommunityTarget()) {
            return $this->hasCommunityToken() || $this->hasUserToken();
        }

        return $this->hasUserToken();
    }

    /**
     * Sync (wall.get) needs a user token — community tokens get error 27.
     */
    public function canSync(): bool
    {
        return $this->isEnabled()
            && '' !== $this->syncOwnerIdRaw()
            && $this->hasUserToken();
    }

    public function isConnected(): bool
    {
        return $this->canCrosspost();
    }
}
