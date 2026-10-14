<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Inquiry;
use App\Enum\ContactType;
use App\Enum\InquiryStatus;
use App\Enum\InquiryType;
use App\Service\InquiryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class InquiryServiceTest extends KernelTestCase
{
    public function testCreatePersistsInquiryWithConsentAndNewStatus(): void
    {
        self::bootKernel();
        $service = static::getContainer()->get(InquiryService::class);

        $inquiry = $service->create(
            name: 'Ира',
            contact: 'ira-'.bin2hex(random_bytes(3)).'@example.test',
            contactType: ContactType::Email,
            inquiryType: InquiryType::Unsure,
            message: 'Хочу уточнить формат работы',
        );

        self::assertNotNull($inquiry->getId());
        self::assertSame(InquiryStatus::New, $inquiry->getStatus());
        self::assertNotNull($inquiry->getPrivacyConsentAt());
        self::assertSame('Ира', $inquiry->getName());
        self::assertNull($inquiry->getAttachmentPath());
    }

    public function testCreateStoresAttachmentWhenProvided(): void
    {
        self::bootKernel();
        $service = static::getContainer()->get(InquiryService::class);

        $tmp = tempnam(sys_get_temp_dir(), 'inq');
        self::assertNotFalse($tmp);
        file_put_contents($tmp, '%PDF-1.4 test');
        $upload = new UploadedFile($tmp, 'brief.pdf', 'application/pdf', null, true);

        $inquiry = $service->create(
            name: 'Кирилл',
            contact: '@kirill_test_'.bin2hex(random_bytes(2)),
            contactType: ContactType::Telegram,
            inquiryType: InquiryType::Consultation,
            message: 'Во вложении бриф',
            attachment: $upload,
        );

        self::assertNotNull($inquiry->getAttachmentPath());
        self::assertSame('brief.pdf', $inquiry->getAttachmentOriginalName());
        self::assertNotNull($inquiry->getAttachmentMimeType());
    }

    public function testCreatePracticeInquiryType(): void
    {
        self::bootKernel();
        /** @var InquiryService $service */
        $service = static::getContainer()->get(InquiryService::class);
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $inquiry = $service->create(
            name: 'Лена',
            contact: 'lena-'.bin2hex(random_bytes(3)).'@example.test',
            contactType: ContactType::Email,
            inquiryType: InquiryType::Practice,
            message: 'Практика · разовая',
        );

        $fresh = $em->find(Inquiry::class, $inquiry->getId());
        self::assertSame(InquiryType::Practice, $fresh?->getInquiryType());
    }
}
