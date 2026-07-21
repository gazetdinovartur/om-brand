<?php

namespace App\Service;

use App\Entity\ChronicleEntry;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Gates public chronicle URLs: admin-only entries resolve as 404 for everyone else.
 */
class ChronicleWebAccess
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function assertCanView(ChronicleEntry $entry): void
    {
        if (!$entry->isAdminOnly() || $this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        throw new NotFoundHttpException('Запись не найдена.');
    }
}
