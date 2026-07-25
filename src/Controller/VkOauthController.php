<?php

namespace App\Controller;

use App\Service\VkApiClient;
use App\Service\VkApiException;
use App\Service\VkCredentials;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * VK wall-token connect.
 *
 * Scope «wall» доступен только Standalone + redirect_uri=blank.html (implicit token).
 * Кастомный /oauth/vk/callback с response_type=code даёт Security Error.
 */
#[IsGranted('ROLE_ADMIN')]
final class VkOauthController extends AbstractController
{
    private const AUTHORIZE = 'https://oauth.vk.com/authorize';
    private const BLANK = 'https://oauth.vk.com/blank.html';

    public function __construct(
        private readonly VkCredentials $credentials,
        private readonly VkApiClient $vk,
    ) {
    }

    #[Route('/admin/oauth/vk/start', name: 'admin_oauth_vk_start', methods: ['GET'])]
    public function start(): Response
    {
        $appId = $this->credentials->appId();
        if ('' === $appId) {
            $this->addFlash('danger', 'Задайте VK_APP_ID в .env');

            return $this->redirectToRoute('admin');
        }

        $authorizeUrl = self::AUTHORIZE.'?'.http_build_query([
            'client_id' => $appId,
            'display' => 'page',
            'redirect_uri' => self::BLANK,
            'scope' => 'wall,photos,offline',
            'response_type' => 'token',
            'v' => '5.199',
        ]);

        return $this->render('admin/vk_oauth_connect.html.twig', [
            'authorizeUrl' => $authorizeUrl,
            'blankUri' => self::BLANK,
            'connected' => $this->credentials->hasUserToken(),
            'ownerId' => $this->credentials->ownerId(),
        ]);
    }

    #[Route('/admin/oauth/vk/token', name: 'admin_oauth_vk_token', methods: ['POST'])]
    public function saveToken(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('vk_oauth_token', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('danger', 'CSRF: обновите страницу и попробуйте снова.');

            return $this->redirectToRoute('admin_oauth_vk_start');
        }

        $raw = trim((string) $request->request->get('token_payload', ''));
        $token = $this->extractAccessToken($raw);
        if (null === $token) {
            $this->addFlash('danger', 'Не нашёл access_token. Вставьте фрагмент из адресной строки blank.html целиком.');

            return $this->redirectToRoute('admin_oauth_vk_start');
        }

        try {
            $this->credentials->storeUserAccessToken($token);
            $ownerId = $this->vk->resolveOwnerId();

            return $this->render('admin/vk_oauth_result.html.twig', [
                'ok' => true,
                'message' => 'Токен VK сохранён. Можно публиковать хронику на стену.',
                'userId' => $ownerId,
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
     * Accepts raw access_token, full blank.html URL, or #access_token=… fragment.
     */
    private function extractAccessToken(string $raw): ?string
    {
        if ('' === $raw) {
            return null;
        }

        if (preg_match('/access_token=([^&\s#]+)/', $raw, $m)) {
            return rawurldecode($m[1]);
        }

        // Bare token (no & or =).
        if (!str_contains($raw, '=') && !str_contains($raw, ' ') && \strlen($raw) > 20) {
            return $raw;
        }

        return null;
    }
}
