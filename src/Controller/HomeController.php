<?php

namespace App\Controller;

use App\Form\InquiryFormType;
use App\Repository\CaseStudyRepository;
use App\Repository\SiteSettingsRepository;
use App\Service\InquiryService;
use App\Service\LandingContentProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'web_home', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        LandingContentProvider $contentProvider,
        SiteSettingsRepository $siteSettingsRepository,
        CaseStudyRepository $caseStudyRepository,
        InquiryService $inquiryService,
    ): Response {
        $settings = $siteSettingsRepository->getSettings();
        $form = $this->createForm(InquiryFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ('' !== (string) $form->get('website')->getData()) {
                return $this->redirectToRoute('web_home', ['_fragment' => 'contact']);
            }

            if ($form->isValid()) {
                $data = $form->getData();
                $inquiryService->create(
                    name: $data['name'],
                    contact: $data['contact'],
                    contactType: $data['contactType'],
                    inquiryType: $data['inquiryType'],
                    message: $data['message'],
                    attachment: $form->get('attachment')->getData(),
                );

                $successMessage = $settings->getFormSuccessMessage()
                    ?: 'Вижу тебя. Скоро встретимся — выйду на связь.';

                $this->addFlash('success', $successMessage);

                return $this->redirectToRoute('web_home', ['_fragment' => 'contact']);
            }
        }

        return $this->render('web/home/index.html.twig', [
            'settings' => $settings,
            'blocks' => $contentProvider->getVisibleBlocks(),
            'blocksBySlug' => $this->indexBlocks($contentProvider->getVisibleBlocks()),
            'cases' => $caseStudyRepository->findPublishedOrdered(),
            'form' => $form,
        ]);
    }

    /**
     * @param iterable<\App\Entity\ContentBlock> $blocks
     *
     * @return array<string, \App\Entity\ContentBlock>
     */
    private function indexBlocks(iterable $blocks): array
    {
        $indexed = [];
        foreach ($blocks as $block) {
            $indexed[$block->getSlug()] = $block;
        }

        return $indexed;
    }
}
