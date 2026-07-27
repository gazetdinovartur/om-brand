<?php

namespace App\Controller;

use App\Service\PushSubscriptionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class PushSubscribeController extends AbstractController
{
    #[Route('/api/push/subscribe', name: 'web_push_subscribe', methods: ['POST'])]
    public function subscribe(
        Request $request,
        PushSubscriptionService $push,
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

        $response = new JsonResponse();
        $result = $push->subscribe($payload, $request, $response);
        $response->setData($result);
        $response->setStatusCode(($result['ok'] ?? false) ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);

        return $response;
    }

    #[Route('/api/push/unsubscribe', name: 'web_push_unsubscribe', methods: ['POST'])]
    public function unsubscribe(
        Request $request,
        PushSubscriptionService $push,
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

        $result = $push->unsubscribe($payload);

        return $this->json($result, ($result['ok'] ?? false) ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
    }
}
