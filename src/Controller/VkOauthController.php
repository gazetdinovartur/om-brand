<?php

namespace App\Controller;

use App\Service\VkApiClient;
use App\Service\VkApiException;
use App\Service\VkCredentials;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class VkOauthController extends AbstractController
{
    public function __construct(
        private readonly VkCredentials $credentials,
        private readonly VkApiClient $vk,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(default:app.site_url:APP_SITE_URL)%')]
        private readonly string $siteUrl = '',
    ) {
    }

    #[Route('/admin/oauth/vk/start', name: 'admin_oauth_vk_start', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function start(Request $request): Response
    {
        $appId = $this->credentials->appId();
        if ('' === $appId) {
            $this->addFlash('danger', 'Задайте VK_APP_ID в .env');

            return $this->redirectToRoute('admin');
        }

        $state = bin2hex(random_bytes(16));
        $request->getSession()->set('vk_oauth_state', $state);

        $redirectUri = $this->callbackUrl();
        $query = http_build_query([
            'client_id' => $appId,
            'display' => 'page',
            'redirect_uri' => $redirectUri,
            'scope' => 'wall,photos,offline',
            'response_type' => 'code',
            'v' => '5.199',
            'state' => $state,
        ]);

        return $this->redirect('https://oauth.vk.com/authorize?'.$query);
    }

    #[Route('/oauth/vk/callback', name: 'oauth_vk_callback', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function callback(Request $request): Response
    {
        $session = $request->getSession();
        $expected = (string) $session->get('vk_oauth_state', '');
        $state = (string) $request->query->get('state', '');
        $session->remove('vk_oauth_state');

        if ('' === $expected || !hash_equals($expected, $state)) {
            return $this->render('admin/vk_oauth_result.html.twig', [
                'ok' => false,
                'message' => 'Неверный state (CSRF). Запустите подключение VK снова.',
            ]);
        }

        if ($request->query->has('error')) {
            return $this->render('admin/vk_oauth_result.html.twig', [
                'ok' => false,
                'message' => (string) $request->query->get('error_description', $request->query->get('error')),
            ]);
        }

        $code = (string) $request->query->get('code', '');
        if ('' === $code) {
            return $this->render('admin/vk_oauth_result.html.twig', [
                'ok' => false,
                'message' => 'VK не вернул code.',
            ]);
        }

        try {
            $token = $this->vk->exchangeCode($code, $this->callbackUrl());
            $this->credentials->storeUserAccessToken($token['access_token']);

            return $this->render('admin/vk_oauth_result.html.twig', [
                'ok' => true,
                'message' => 'Токен VK сохранён. Можно публиковать хронику на стену.',
                'userId' => $token['user_id'] ?? null,
            ]);
        } catch (VkApiException $e) {
            return $this->render('admin/vk_oauth_result.html.twig', [
                'ok' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function callbackUrl(): string
    {
        $base = rtrim($this->siteUrl, '/');
        if ('' !== $base) {
            return $base.'/oauth/vk/callback';
        }

        return $this->urlGenerator->generate(
            'oauth_vk_callback',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
