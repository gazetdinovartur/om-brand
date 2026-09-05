<?php

namespace App\Controller;

use App\Service\VkApiClient;
use App\Service\VkApiException;
use App\Service\VkCredentials;
use App\Service\VkIdOAuth;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * VK user-token connect via VK ID PKCE.
 * redirect_uri = {APP_SITE_URL}/admin/oauth/vk/callback — must be listed in id.vk.ru app settings.
 */
#[IsGranted('ROLE_ADMIN')]
final class VkOauthController extends AbstractController
{
    private const SESSION_VERIFIER = 'vk_id_code_verifier';
    private const SESSION_STATE = 'vk_id_state';

    public function __construct(
        private readonly VkCredentials $credentials,
        private readonly VkApiClient $vk,
        private readonly VkIdOAuth $vkId,
    ) {
    }

    #[Route('/admin/oauth/vk/start', name: 'admin_oauth_vk_start', methods: ['GET'])]
    public function start(Request $request): Response
    {
        $appId = $this->credentials->appId();
        if ('' === $appId) {
            $this->addFlash('danger', 'Задайте VK_APP_ID в .env (приложение из id.vk.ru).');

            return $this->redirectToRoute('admin');
        }

        try {
            $redirectUri = $this->vkId->redirectUri();
        } catch (VkApiException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('admin');
        }

        $pkce = $this->vkId->createPkceSession();
        $session = $request->getSession();
        $session->set(self::SESSION_VERIFIER, $pkce['verifier']);
        $session->set(self::SESSION_STATE, $pkce['state']);

        $scope = 'vkid.personal_info';
        $authorizeUrl = $this->vkId->buildAuthorizeUrl($pkce['challenge'], $pkce['state'], $scope);

        return $this->render('admin/vk_oauth_connect.html.twig', [
            'authorizeUrl' => $authorizeUrl,
            'redirectUri' => $redirectUri,
            'scopes' => $scope,
            'connected' => $this->credentials->hasUserToken(),
            'ownerId' => $this->credentials->ownerId(),
            'syncOwnerId' => $this->credentials->syncOwnerId(),
        ]);
    }

    /**
     * VK redirects here after consent. Minimal processing — no external assets on this hop.
     */
    #[Route('/admin/oauth/vk/callback', name: 'admin_oauth_vk_callback', methods: ['GET'])]
    public function callback(Request $request): Response
    {
        $session = $request->getSession();

        try {
            $auth = $this->vkId->extractAuthCodeFromRequest($request->query->all());
            if (null === $auth) {
                throw new VkApiException('В callback нет code/device_id. Проверь Trusted redirect URI в id.vk.ru.');
            }

            $verifier = (string) $session->get(self::SESSION_VERIFIER, '');
            if ('' === $verifier) {
                throw new VkApiException('Сессия PKCE пуста. Начни с /admin/oauth/vk/start в том же браузере.');
            }

            $expectedState = (string) $session->get(self::SESSION_STATE, '');
            if ('' !== $expectedState && null !== $auth['state'] && $auth['state'] !== $expectedState) {
                throw new VkApiException('state не совпал — возможна подмена ответа.');
            }

            $exchanged = $this->vkId->exchangeCode(
                $auth['code'],
                $auth['device_id'],
                $verifier,
                $auth['state'],
            );
            $this->persistToken($exchanged);
            $session->remove(self::SESSION_VERIFIER);
            $session->remove(self::SESSION_STATE);

            return $this->render('admin/vk_oauth_result.html.twig', [
                'ok' => true,
                'message' => 'User-токен VK ID сохранён. Задай VK_SYNC_OWNER_ID и проверь sync. При ошибке 1051 на api.vk.com — импорт стены через экспорт VK.',
                'userId' => $exchanged['user_id'] ?? $this->safeResolveUserId(),
            ]);
        } catch (VkApiException $e) {
            return $this->render('admin/vk_oauth_result.html.twig', [
                'ok' => false,
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            return $this->render('admin/vk_oauth_result.html.twig', [
                'ok' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    #[Route('/admin/oauth/vk/token', name: 'admin_oauth_vk_token', methods: ['POST'])]
    public function saveToken(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('vk_oauth_token', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('danger', 'CSRF: обновите страницу и попробуйте снова.');

            return $this->redirectToRoute('admin_oauth_vk_start');
        }

        $raw = trim((string) $request->request->get('token_payload', ''));
        $session = $request->getSession();

        try {
            $auth = $this->vkId->extractAuthCodePayload($raw);
            if (null !== $auth) {
                $verifier = (string) $session->get(self::SESSION_VERIFIER, '');
                if ('' === $verifier) {
                    throw new VkApiException('Сессия PKCE истекла. Открой «Подключить VK» заново.');
                }
                $expectedState = (string) $session->get(self::SESSION_STATE, '');
                if ('' !== $expectedState && null !== $auth['state'] && $auth['state'] !== $expectedState) {
                    throw new VkApiException('state не совпал — начни подключение заново.');
                }

                $exchanged = $this->vkId->exchangeCode(
                    $auth['code'],
                    $auth['device_id'],
                    $verifier,
                    $auth['state'],
                );
                $this->persistToken($exchanged);
                $userId = $exchanged['user_id'] ?? $this->safeResolveUserId();
            } else {
                $token = $this->extractLegacyAccessToken($raw);
                if (null === $token) {
                    throw new VkApiException('Не нашёл code/device_id и не нашёл access_token.');
                }
                $this->credentials->storeUserAccessToken($token);
                $userId = $this->safeResolveUserId();
            }

            $session->remove(self::SESSION_VERIFIER);
            $session->remove(self::SESSION_STATE);

            return $this->render('admin/vk_oauth_result.html.twig', [
                'ok' => true,
                'message' => 'User-токен сохранён.',
                'userId' => $userId,
            ]);
        } catch (VkApiException $e) {
            return $this->render('admin/vk_oauth_result.html.twig', [
                'ok' => false,
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            return $this->render('admin/vk_oauth_result.html.twig', [
                'ok' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array{access_token: string, refresh_token: ?string, user_id: ?int, expires_in: ?int, device_id: string} $exchanged
     */
    private function persistToken(array $exchanged): void
    {
        $this->credentials->storeUserAccessToken($exchanged['access_token']);
        if (!empty($exchanged['refresh_token'])) {
            $this->credentials->storeRefreshMeta(
                (string) $exchanged['refresh_token'],
                $exchanged['device_id'],
            );
        }
    }

    private function safeResolveUserId(): ?int
    {
        try {
            return $this->vk->resolveOwnerId();
        } catch (\Throwable) {
            return $this->credentials->syncOwnerId() ?? $this->credentials->ownerId();
        }
    }

    private function extractLegacyAccessToken(string $raw): ?string
    {
        if ('' === $raw) {
            return null;
        }

        if (preg_match('/access_token=([^&\s#]+)/', $raw, $m)) {
            return rawurldecode($m[1]);
        }

        if (!str_contains($raw, '=') && !str_contains($raw, ' ') && \strlen($raw) > 20) {
            return $raw;
        }

        return null;
    }
}
