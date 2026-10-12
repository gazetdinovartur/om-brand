<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\AdminUser;
use App\Entity\WebauthnCredential;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

final class PasskeyDomainTest extends TestCase
{
    public function testAdminUsersReceiveDistinctStableHandles(): void
    {
        $first = new AdminUser();
        $second = new AdminUser();

        self::assertNotSame($first->getWebauthnUserHandle(), $second->getWebauthnUserHandle());
        self::assertTrue(Uuid::isValid($first->getWebauthnUserHandle()));
    }

    public function testCredentialConversionPreservesVerificationData(): void
    {
        $record = new CredentialRecord(
            'credential-binary-id',
            'public-key',
            ['internal'],
            'none',
            EmptyTrustPath::create(),
            Uuid::fromString('00000000-0000-0000-0000-000000000000'),
            'public-key-binary',
            'user-handle',
            7,
            ['device' => 'test'],
            true,
            true,
            true,
        );

        $credential = WebauthnCredential::fromCredentialRecord($record);

        self::assertSame(hash('sha256', 'credential-binary-id'), $credential->getCredentialIdHash());
        self::assertSame('user-handle', $credential->userHandle);
        self::assertSame(7, $credential->counter);
        self::assertTrue($credential->backupStatus);
    }
}
