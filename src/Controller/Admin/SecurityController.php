<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/admin/login', name: 'admin_login')]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        $targetPath = $this->safeTargetPath($request->query->getString('_target_path'));
        // Shared context may still hold a library SiteUser — only admins skip the form.
        if ($this->isGranted('ROLE_ADMIN')) {
            return null !== $targetPath
                ? $this->redirect($targetPath)
                : $this->redirectToRoute('admin');
        }

        return $this->render('admin/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'target_path' => $targetPath ?? $this->generateUrl('admin'),
        ]);
    }

    #[Route('/admin/logout', name: 'admin_logout')]
    public function logout(): void
    {
        throw new \LogicException('Logout is handled by Symfony security.');
    }

    private function safeTargetPath(string $targetPath): ?string
    {
        if (
            '' === $targetPath
            || !str_starts_with($targetPath, '/')
            || str_starts_with($targetPath, '//')
            || str_contains($targetPath, '\\')
        ) {
            return null;
        }

        return $targetPath;
    }
}
