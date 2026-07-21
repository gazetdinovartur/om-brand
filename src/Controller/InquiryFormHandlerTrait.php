<?php

namespace App\Controller;

use App\Controller\Form\FormErrorCollector;
use App\Service\InquiryService;
use App\Service\PublicSiteContext;
use App\Validation\ContactValueValidator;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;

trait InquiryFormHandlerTrait
{
    /**
     * @param array<string, list<string>>|null $errors
     */
    private function respondInquiry(
        Request $request,
        bool $ok,
        string $message,
        string $redirectRoute,
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

        return $this->redirectToRoute($redirectRoute);
    }

    private function wantsJsonResponse(Request $request): bool
    {
        return $request->isXmlHttpRequest()
            || str_contains($request->headers->get('Accept', ''), 'application/json');
    }

    private function handleInquirySubmission(
        Request $request,
        FormInterface $form,
        InquiryService $inquiryService,
        RateLimiterFactory $inquiryLimiterFactory,
        PublicSiteContext $siteContext,
        string $redirectRoute,
    ): ?Response {
        if (!$form->isSubmitted()) {
            return null;
        }

        $limiter = $inquiryLimiterFactory->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->respondInquiry(
                $request,
                ok: false,
                message: 'Слишком много попыток. Подождите 15 минут и попробуйте снова.',
                redirectRoute: $redirectRoute,
                status: Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        if ('' !== (string) $form->get('website')->getData()) {
            return $this->respondInquiry($request, ok: true, message: '', redirectRoute: $redirectRoute);
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
                        redirectRoute: $redirectRoute,
                        status: Response::HTTP_INTERNAL_SERVER_ERROR,
                    );
                }

                throw $exception;
            }

            $settings = $siteContext->getSettings();
            $successMessage = $settings->getFormSuccessMessage()
                ?: 'Благодарю за ваш запрос! Скоро вернусь с обратной связью';

            return $this->respondInquiry($request, ok: true, message: $successMessage, redirectRoute: $redirectRoute);
        }

        if ($this->wantsJsonResponse($request)) {
            return $this->respondInquiry(
                $request,
                ok: false,
                message: 'Проверьте выделенные поля',
                redirectRoute: $redirectRoute,
                errors: FormErrorCollector::collect($form),
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return null;
    }
}
