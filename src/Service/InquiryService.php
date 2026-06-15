<?php

namespace App\Service;

use App\Entity\Inquiry;
use App\Enum\ContactType;
use App\Enum\InquiryStatus;
use App\Enum\InquiryType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class InquiryService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InquiryAttachmentStorage $attachmentStorage,
        private readonly TelegramNotifier $telegramNotifier,
    ) {
    }

    public function create(
        string $name,
        string $contact,
        ContactType $contactType,
        InquiryType $inquiryType,
        string $message,
        ?UploadedFile $attachment = null,
    ): Inquiry {
        $inquiry = (new Inquiry())
            ->setName($name)
            ->setContact($contact)
            ->setContactType($contactType)
            ->setInquiryType($inquiryType)
            ->setMessage($message)
            ->setStatus(InquiryStatus::New);

        if ($attachment instanceof UploadedFile) {
            $stored = $this->attachmentStorage->store($attachment);
            $inquiry
                ->setAttachmentPath($stored['path'])
                ->setAttachmentOriginalName($stored['originalName'])
                ->setAttachmentMimeType($stored['mimeType']);
        }

        $this->entityManager->persist($inquiry);
        $this->entityManager->flush();

        $this->telegramNotifier->notifyNewInquiry($inquiry);

        return $inquiry;
    }
}
