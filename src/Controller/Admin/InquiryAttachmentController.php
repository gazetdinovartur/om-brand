<?php

namespace App\Controller\Admin;

use App\Entity\Inquiry;
use App\Service\InquiryAttachmentStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class InquiryAttachmentController extends AbstractController
{
    #[Route('/admin/inquiries/{id}/attachment', name: 'admin_inquiry_attachment', methods: ['GET'])]
    public function download(Inquiry $inquiry, InquiryAttachmentStorage $storage): Response
    {
        if (!$inquiry->hasAttachment()) {
            throw $this->createNotFoundException('Файл не найден.');
        }

        $path = $storage->getAbsolutePath((string) $inquiry->getAttachmentPath());
        if (!is_file($path)) {
            throw $this->createNotFoundException('Файл не найден на диске.');
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            (string) $inquiry->getAttachmentOriginalName()
        );

        return $response;
    }
}
