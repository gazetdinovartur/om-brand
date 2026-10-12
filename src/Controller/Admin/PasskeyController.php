<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AdminUser;
use App\Entity\WebauthnCredential;
use App\Repository\WebauthnCredentialRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/passkeys')]
#[IsGranted('ROLE_ADMIN')]
final class PasskeyController extends AbstractController
{
    public function __construct(
        private readonly WebauthnCredentialRepository $credentials,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'admin_passkey_index', methods: ['GET'])]
    public function index(): Response
    {
        $admin = $this->currentAdmin();
        $response = $this->render('admin/passkeys/index.html.twig', [
            'credentials' => $this->credentials->findForAdmin($admin),
        ]);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }

    #[Route('/{id}/rename', name: 'admin_passkey_rename', methods: ['POST'])]
    public function rename(string $id, Request $request): RedirectResponse
    {
        $credential = $this->ownedCredential($id);
        if (!$this->isCsrfTokenValid('passkey_rename_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Сессия устарела.');
        }

        $label = trim($request->request->getString('label'));
        if ('' === $label) {
            $this->addFlash('danger', 'Укажите название ключа.');

            return $this->redirectToRoute('admin_passkey_index');
        }
        $length = iconv_strlen($label, 'UTF-8');
        if (false !== $length && $length > 120) {
            $label = (string) iconv_substr($label, 0, 120, 'UTF-8');
        }

        $credential->setLabel($label);
        $this->entityManager->flush();
        $this->addFlash('success', 'Название ключа сохранено.');

        return $this->redirectToRoute('admin_passkey_index');
    }

    #[Route('/{id}/delete', name: 'admin_passkey_delete', methods: ['POST'])]
    public function delete(string $id, Request $request): RedirectResponse
    {
        $credential = $this->ownedCredential($id);
        if (!$this->isCsrfTokenValid('passkey_delete_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Сессия устарела.');
        }

        $this->entityManager->remove($credential);
        $this->entityManager->flush();
        $this->addFlash('success', 'Ключ доступа удалён.');

        return $this->redirectToRoute('admin_passkey_index');
    }

    private function currentAdmin(): AdminUser
    {
        $admin = $this->getUser();
        if (!$admin instanceof AdminUser) {
            throw $this->createAccessDeniedException();
        }

        return $admin;
    }

    private function ownedCredential(string $id): WebauthnCredential
    {
        $credential = $this->credentials->find($id);
        if (
            !$credential instanceof WebauthnCredential
            || $credential->userHandle !== $this->currentAdmin()->getWebauthnUserHandle()
        ) {
            throw $this->createNotFoundException('Ключ доступа не найден.');
        }

        return $credential;
    }
}
