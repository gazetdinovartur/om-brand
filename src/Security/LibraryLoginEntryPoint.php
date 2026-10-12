<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final readonly class LibraryLoginEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        $targetPath = $request->getRequestUri();
        if (!str_starts_with($targetPath, '/') || str_starts_with($targetPath, '//')) {
            $targetPath = '/library';
        }

        // Админские маршруты по-прежнему ведут на admin login.
        if (str_starts_with($targetPath, '/admin')) {
            return new RedirectResponse($this->urlGenerator->generate('admin_login', [
                '_target_path' => $targetPath,
            ]));
        }

        return new RedirectResponse($this->urlGenerator->generate('web_library_login', [
            '_target_path' => $targetPath,
        ]));
    }
}
