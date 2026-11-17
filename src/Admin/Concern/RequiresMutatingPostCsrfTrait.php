<?php

declare(strict_types=1);

namespace App\Admin\Concern;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

trait RequiresMutatingPostCsrfTrait
{
    private function assertMutatingAdminPost(AdminContext $context, string $actionName): void
    {
        $request = $context->getRequest();
        if (!$request->isMethod('POST')) {
            throw new AccessDeniedHttpException('Это действие доступно только через POST.');
        }

        $token = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('admin_mutating_'.$actionName, $token)) {
            throw new AccessDeniedHttpException('Недействительный CSRF-токен.');
        }
    }
}
