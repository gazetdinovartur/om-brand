<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\SiteUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

/**
 * Shared security context: a library SiteUser is "authenticated" for /admin too,
 * but lacks ROLE_ADMIN — Symfony then returns 403 instead of the login form.
 * Send readers to admin login and drop their token so the form can authenticate an AdminUser.
 */
final readonly class AdminAccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if ($user instanceof SiteUser) {
            $this->tokenStorage->setToken(null);
            if ($request->hasSession()) {
                $request->getSession()->getFlashBag()->add(
                    'warning',
                    'Вы вошли как читатель библиотеки. Для админки нужен вход администратора.',
                );
            }
        }

        $targetPath = $request->getRequestUri();
        if (!str_starts_with($targetPath, '/') || str_starts_with($targetPath, '//') || str_starts_with($targetPath, '/admin/login')) {
            $targetPath = '/admin';
        }

        return new RedirectResponse($this->urlGenerator->generate('admin_login', [
            '_target_path' => $targetPath,
        ]));
    }
}
