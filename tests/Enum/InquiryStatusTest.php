<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\InquiryStatus;
use PHPUnit\Framework\TestCase;

final class InquiryStatusTest extends TestCase
{
    public function testOnlyNewAndDoneRemain(): void
    {
        self::assertSame(
            [InquiryStatus::New, InquiryStatus::Done],
            InquiryStatus::cases(),
        );
    }

    public function testLabelsAreSoloAdminFriendly(): void
    {
        self::assertSame('Новая', InquiryStatus::New->label());
        self::assertSame('Закрыта', InquiryStatus::Done->label());
    }
}
