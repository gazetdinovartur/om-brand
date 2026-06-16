<?php

namespace App\Controller;

use App\Content\LandingContent;
use App\Controller\Form\FormErrorCollector;
use App\Form\InquiryFormType;
use App\Repository\CaseStudyRepository;
use App\Service\InquiryService;
use App\Service\PublicSiteContext;
use App\Validation\ContactValueValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'web_home', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        PublicSiteContext $siteContext,
        CaseStudyRepository $caseStudyRepository,
        InquiryService $inquiryService,
        #[Autowire('@limiter.inquiry_form')]
        RateLimiterFactory $inquiryLimiterFactory,
    ): Response {
        $settings = $siteContext->getSettings();
        $blocksBySlug = $siteContext->getBlocksBySlug();
        $cases = $caseStudyRepository->findPublishedOrdered();
        $form = $this->createForm(InquiryFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $limiter = $inquiryLimiterFactory->create($request->getClientIp() ?? 'unknown');
            if (!$limiter->consume(1)->isAccepted()) {
                return $this->respondInquiry(
                    $request,
                    ok: false,
                    message: 'Слишком много попыток. Подождите 15 минут и попробуйте снова.',
                    status: Response::HTTP_TOO_MANY_REQUESTS,
                );
            }

            if ('' !== (string) $form->get('website')->getData()) {
                return $this->respondInquiry($request, ok: true, message: '');
            }

            if ($form->isValid()) {
                $data = $form->getData();
                try {
                    $inquiryService->create(
                        name: trim($data['name']),
                        contact: ContactValueValidator::normalize($data['contact'], $data['contactType']),
                        contactType: $data['contactType'],
                        inquiryType: $data['inquiryType'],
                        message: trim($data['message'] ?? ''),
                        attachment: $form->get('attachment')->getData(),
                    );
                } catch (\Throwable $exception) {
                    if ($this->wantsJsonResponse($request)) {
                        return $this->respondInquiry(
                            $request,
                            ok: false,
                            message: 'Не удалось сохранить заявку. Попробуйте ещё раз чуть позже.',
                            status: Response::HTTP_INTERNAL_SERVER_ERROR,
                        );
                    }

                    throw $exception;
                }

                $successMessage = $settings->getFormSuccessMessage()
                    ?: 'Благодарю за ваш запрос! Скоро вернусь с обратной связью';

                return $this->respondInquiry($request, ok: true, message: $successMessage);
            }

            if ($this->wantsJsonResponse($request)) {
                return $this->respondInquiry(
                    $request,
                    ok: false,
                    message: 'Проверьте выделенные поля',
                    errors: FormErrorCollector::collect($form),
                    status: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }
        }

        return $this->render('web/home/index.html.twig', [
            'settings' => $settings,
            'blocks' => $siteContext->getVisibleBlocks(),
            'blocksBySlug' => $blocksBySlug,
            'cases' => $cases,
            'navAnchors' => LandingContent::navigationAnchors(\count($cases) > 0),
            'form' => $form,
        ]);
    }

    /**
     * @param array<string, list<string>>|null $errors
     */
    private function respondInquiry(
        Request $request,
        bool $ok,
        string $message,
        ?array $errors = null,
        int $status = Response::HTTP_OK,
    ): Response {
        if ($this->wantsJsonResponse($request)) {
            $payload = ['ok' => $ok, 'message' => $message];
            if (null !== $errors) {
                $payload['errors'] = $errors;
            }

            return $this->json($payload, $status);
        }

        if ($ok && '' !== $message) {
            $this->addFlash('success', $message);
        }

        return $this->redirectToRoute('web_home');
    }

    private function wantsJsonResponse(Request $request): bool
    {
        return $request->isXmlHttpRequest()
            || str_contains($request->headers->get('Accept', ''), 'application/json');
    }
}
