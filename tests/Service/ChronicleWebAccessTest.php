<?php

namespace App\Tests\Service;

use App\Entity\ChronicleEntry;
use App\Service\ChronicleWebAccess;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ChronicleWebAccessTest extends TestCase
{
    public function testPublicEntryAllowedForEveryone(): void
    {
        $entry = new ChronicleEntry();
        $entry->setIsAdminOnly(false);

        $security = $this->createMock(Security::class);
        $security->expects(self::never())->method('isGranted');

        (new ChronicleWebAccess($security))->assertCanView($entry);
        self::addToAssertionCount(1);
    }

    public function testAdminOnlyEntryDeniedForGuest(): void
    {
        $entry = new ChronicleEntry();
        $entry->setIsAdminOnly(true);

        $security = $this->createMock(Security::class);
        $security->method('isGranted')->with('ROLE_ADMIN')->willReturn(false);

        $this->expectException(NotFoundHttpException::class);
        (new ChronicleWebAccess($security))->assertCanView($entry);
    }

    public function testAdminOnlyEntryAllowedForAdmin(): void
    {
        $entry = new ChronicleEntry();
        $entry->setIsAdminOnly(true);

        $security = $this->createMock(Security::class);
        $security->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);

        (new ChronicleWebAccess($security))->assertCanView($entry);
        self::addToAssertionCount(1);
    }
}
