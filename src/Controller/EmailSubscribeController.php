<?php

namespace App\Controller;

use App\Seo\SeoMetadata;
use App\Seo\SeoMetadataFactory;
use App\Service\EmailSubscriptionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class EmailSubscribeController extends AbstractController
{
    #[Route('/api/email/subscribe', name: 'web_email_subscribe', methods: ['POST'])]
    public function subscribe(
        Request $request,
        EmailSubscriptionService $emails,
        #[Autowire('@limiter.subscribe_form')]
        RateLimiterFactory $limiterFactory,
    ): JsonResponse {
        $token = (string) $request->headers->get('X-CSRF-TOKEN', '');
        if (!$this->isCsrfTokenValid('subscribe', $token)) {
            return $this->json(['ok' => false, 'message' => 'Недействительный CSRF-токен.'], Response::HTTP_FORBIDDEN);
        }

        $limiter = $limiterFactory->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->json([
                'ok' => false,
                'message' => 'Слишком много попыток. Подождите немного.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        if (!is_array($payload)) {
            $payload = [];
        }

        $result = $emails->requestSubscribe((string) ($payload['email'] ?? ''));
        $status = ($result['ok'] ?? false) ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST;

        return $this->json($result, $status);
    }

    #[Route('/subscribe/email/confirm/{token}', name: 'web_email_subscribe_confirm', methods: ['GET'], requirements: ['token' => '[a-f0-9]{64}'])]
    public function confirm(
        string $token,
        Request $request,
        EmailSubscriptionService $emails,
        SeoMetadataFactory $seoFactory,
    ): Response {
        $subscriber = $emails->confirm($token);
        $ok = null !== $subscriber;
        $baseUrl = $seoFactory->resolveBaseUrl($request);

        return $this->render('web/subscribe/email_result.html.twig', [
            'ok' => $ok,
            'mode' => 'confirm',
            'seo' => new SeoMetadata(
                title: $ok ? 'Подписка подтверждена' : 'Ссылка недействительна',
                description: $ok
                    ? 'Вы будете получать письма о новых записях в хронике.'
                    : 'Ссылка подтверждения недействительна или устарела.',
                canonicalUrl: rtrim($baseUrl, '/').$request->getPathInfo(),
                robots: 'noindex, nofollow',
                ogType: 'website',
                ogImageUrl: null,
            ),
        ]);
    }

    #[Route('/subscribe/email/unsubscribe/{token}', name: 'web_email_subscribe_unsubscribe', methods: ['GET'], requirements: ['token' => '[a-f0-9]{64}'])]
    public function unsubscribe(
        string $token,
        Request $request,
        EmailSubscriptionService $emails,
        SeoMetadataFactory $seoFactory,
    ): Response {
        $subscriber = $emails->unsubscribe($token);
        $ok = null !== $subscriber;
        $baseUrl = $seoFactory->resolveBaseUrl($request);

        return $this->render('web/subscribe/email_result.html.twig', [
            'ok' => $ok,
            'mode' => 'unsubscribe',
            'seo' => new SeoMetadata(
                title: $ok ? 'Вы отписались' : 'Ссылка недействительна',
                description: $ok
                    ? 'Письма о новых записях больше не будут приходить.'
                    : 'Ссылка отписки недействительна или устарела.',
                canonicalUrl: rtrim($baseUrl, '/').$request->getPathInfo(),
                robots: 'noindex, nofollow',
                ogType: 'website',
                ogImageUrl: null,
            ),
        ]);
    }
}
