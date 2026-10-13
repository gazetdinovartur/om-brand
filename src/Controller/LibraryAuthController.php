<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AdminUser;
use App\Entity\SiteUser;
use App\Entity\WebauthnCredential;
use App\Repository\ReadingBookmarkRepository;
use App\Repository\ReadingNoteRepository;
use App\Repository\ReadingQuoteRepository;
use App\Repository\WebauthnCredentialRepository;
use App\Security\SiteUserAuthenticator;
use App\Service\SiteEmailLoginService;
use App\Service\SiteUserResolver;
use App\Service\TelegramOAuthClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class LibraryAuthController extends AbstractController
{
    public function __construct(
        private readonly SiteEmailLoginService $emailLogin,
        private readonly TelegramOAuthClient $telegramOAuth,
        private readonly SiteUserResolver $siteUsers,
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly WebauthnCredentialRepository $credentials,
        private readonly ReadingBookmarkRepository $bookmarks,
        private readonly ReadingQuoteRepository $quotes,
        private readonly ReadingNoteRepository $notes,
        #[Autowire(service: 'limiter.library_login')]
        private readonly RateLimiterFactory $loginLimiter,
    ) {
    }

    #[Route('/library/login', name: 'web_library_login', methods: ['GET', 'POST'])]
    public function login(Request $request): Response
    {
        if ($this->getUser() instanceof SiteUser || $this->getUser() instanceof AdminUser) {
            return $this->redirect($this->safeTarget($request) ?? $this->generateUrl('web_library'));
        }

        $targetPath = $this->safeTarget($request) ?? $this->generateUrl('web_library');
        $email = '';
        $step = 'choose';
        $statusMessage = null;
        $errorMessage = match ($request->query->getString('error')) {
            'telegram_unavailable' => 'Вход через Telegram сейчас недоступен.',
            'telegram_denied' => 'Вход через Telegram отменён.',
            'telegram_failed' => 'Не удалось войти через Telegram. Попробуйте ещё раз.',
            default => null,
        };

        if ($request->isMethod('POST')) {
            $limiter = $this->loginLimiter->create($request->getClientIp() ?? 'anon');
            if (!$limiter->consume(1)->isAccepted()) {
                $errorMessage = 'Слишком много попыток. Подождите немного.';
            } else {
                $action = $request->request->getString('action');
                $email = mb_strtolower(trim($request->request->getString('email')));
                $targetPath = $this->safeTargetPath($request->request->getString('_target_path'))
                    ?? $targetPath;

                if ('email_request' === $action) {
                    $result = $this->emailLogin->requestCode($email);
                    if ($result['ok']) {
                        $step = 'email_code';
                        $statusMessage = $result['message'];
                    } else {
                        $errorMessage = $result['message'];
                    }
                } elseif ('email_verify' === $action) {
                    $result = $this->emailLogin->verifyCode($email, $request->request->getString('code'));
                    if ($result['ok'] && ($result['user'] ?? null) instanceof SiteUser) {
                        return $this->loginSiteUser($result['user'], $targetPath);
                    }
                    $step = 'email_code';
                    $errorMessage = $result['message'];
                }
            }
        }

        $response = $this->render('web/library/login.html.twig', [
            'targetPath' => $targetPath,
            'email' => $email,
            'step' => $step,
            'statusMessage' => $statusMessage,
            'errorMessage' => $errorMessage,
            'emailLoginEnabled' => $this->emailLogin->isMailConfigured(),
            'telegramLoginEnabled' => $this->telegramOAuth->isAllowedOnHost($request->getHost()),
            'telegramStartUrl' => $this->generateUrl('web_library_login_telegram', [
                '_target_path' => $targetPath,
            ]),
            'passkeyOptionsUrl' => $this->generateUrl('webauthn.controller.security.main.request.options'),
            'passkeyResultUrl' => $this->generateUrl('webauthn.controller.security.main.request.result'),
        ]);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }

    #[Route('/library/login/telegram', name: 'web_library_login_telegram', methods: ['GET'])]
    public function telegramStart(Request $request): Response
    {
        if ($this->getUser() instanceof SiteUser || $this->getUser() instanceof AdminUser) {
            return $this->redirect($this->safeTarget($request) ?? $this->generateUrl('web_library'));
        }

        if (!$this->telegramOAuth->isAllowedOnHost($request->getHost())) {
            return $this->redirectToRoute('web_library_login', ['error' => 'telegram_unavailable']);
        }

        $limiter = $this->loginLimiter->create($request->getClientIp() ?? 'anon');
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->redirectToRoute('web_library_login', ['error' => 'telegram_failed']);
        }

        $targetPath = $this->safeTarget($request) ?? $this->generateUrl('web_library');
        $session = $request->getSession();
        $authorizeUrl = $this->telegramOAuth->beginAuthorization($session, $targetPath);

        return $this->redirect($authorizeUrl);
    }

    #[Route('/library/login/telegram/callback', name: 'web_library_login_telegram_callback', methods: ['GET'])]
    public function telegramCallback(Request $request): Response
    {
        if ($this->getUser() instanceof SiteUser || $this->getUser() instanceof AdminUser) {
            return $this->redirect($this->generateUrl('web_library'));
        }

        if (!$this->telegramOAuth->isAllowedOnHost($request->getHost())) {
            return $this->redirectToRoute('web_library_login', ['error' => 'telegram_unavailable']);
        }

        if ('' !== $request->query->getString('error')) {
            return $this->redirectToRoute('web_library_login', ['error' => 'telegram_denied']);
        }

        $limiter = $this->loginLimiter->create($request->getClientIp() ?? 'anon');
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->redirectToRoute('web_library_login', ['error' => 'telegram_failed']);
        }

        $result = $this->telegramOAuth->completeAuthorization(
            $request->getSession(),
            $request->query->getString('code'),
            $request->query->getString('state'),
        );

        if (!$result['ok']) {
            return $this->redirectToRoute('web_library_login', ['error' => 'telegram_failed']);
        }

        $user = $this->siteUsers->findOrCreateByTelegram($result['user']);
        $user->markLoggedIn();
        $this->entityManager->flush();

        $targetPath = $this->safeTargetPath((string) ($result['targetPath'] ?? ''))
            ?? $this->generateUrl('web_library');

        return $this->loginSiteUser($user, $targetPath);
    }

    #[Route('/library/logout', name: 'web_library_logout', methods: ['GET'])]
    public function logout(): void
    {
        throw new \LogicException('Logout is handled by Symfony security.');
    }

    #[Route('/library/account', name: 'web_library_account', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function account(Request $request): Response
    {
        $user = $this->siteUsers->resolve($this->getUser());
        if (!$user instanceof SiteUser) {
            throw $this->createAccessDeniedException();
        }

        $accountTab = $request->query->getString('tab', 'reading');
        if (!\in_array($accountTab, ['reading', 'keys'], true)) {
            $accountTab = 'reading';
        }

        if ($request->isMethod('POST')) {
            $credentialId = $request->request->getString('credential_id');
            $credential = $this->credentials->find($credentialId);
            if (
                !$credential instanceof WebauthnCredential
                || $credential->userHandle !== $user->getWebauthnUserHandle()
            ) {
                throw $this->createNotFoundException('Ключ доступа не найден.');
            }

            $action = $request->request->getString('action');
            if ('rename' === $action) {
                if (!$this->isCsrfTokenValid('site_passkey_rename_'.$credentialId, $request->request->getString('_token'))) {
                    throw $this->createAccessDeniedException('Сессия устарела.');
                }
                $label = trim($request->request->getString('label'));
                if ('' === $label) {
                    $this->addFlash('danger', 'Укажите название ключа.');
                } else {
                    $credential->setLabel($label);
                    $this->entityManager->flush();
                    $this->addFlash('success', 'Название ключа сохранено.');
                }
            } elseif ('delete' === $action) {
                if (!$this->isCsrfTokenValid('site_passkey_delete_'.$credentialId, $request->request->getString('_token'))) {
                    throw $this->createAccessDeniedException('Сессия устарела.');
                }
                $this->entityManager->remove($credential);
                $this->entityManager->flush();
                $this->addFlash('success', 'Ключ доступа удалён.');
            }

            return $this->redirectToRoute('web_library_account', ['tab' => 'keys']);
        }

        $response = $this->render('web/library/account.html.twig', [
            'siteUser' => $user,
            'accountTab' => $accountTab,
            'credentials' => $this->credentials->findForSiteUser($user),
            'recentBookmarks' => $this->bookmarks->findRecentForUser($user, 20),
            'recentQuotes' => $this->quotes->findRecentForUser($user, 20),
            'recentNotes' => $this->notes->findRecentForUser($user, 20),
        ]);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }

    private function loginSiteUser(SiteUser $user, string $targetPath): Response
    {
        // Explicit authenticator: main firewall's only HTTP authenticator is WebAuthn,
        // which rejects passwordless email/Telegram login with "Invalid credentials".
        $this->security->login($user, SiteUserAuthenticator::class, 'main');

        return $this->redirect($targetPath);
    }

    private function safeTarget(Request $request): ?string
    {
        return $this->safeTargetPath($request->query->getString('_target_path'));
    }

    private function safeTargetPath(string $targetPath): ?string
    {
        if (
            '' === $targetPath
            || !str_starts_with($targetPath, '/')
            || str_starts_with($targetPath, '//')
            || str_contains($targetPath, '\\')
            || str_starts_with($targetPath, '/admin')
        ) {
            return null;
        }

        return $targetPath;
    }
}
