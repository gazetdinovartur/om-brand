<?php

namespace App\Service;

use App\Admin\InquiryCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

final class InquiryAdminUrlGenerator
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public function detailUrl(int $inquiryId): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController(InquiryCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($inquiryId)
            ->generateUrl();
    }
}
