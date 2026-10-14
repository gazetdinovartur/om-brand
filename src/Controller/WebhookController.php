<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PaymentOfferService;
use App\Service\PracticeKinescopeService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WebhookController extends AbstractController
{
    public function __construct(
        private readonly PaymentOfferService $paymentOfferService,
        private readonly PracticeKinescopeService $practiceKinescope,
        private readonly LoggerInterface $logger,
        private readonly string $kinescopeWebhookUser = '',
        private readonly string $kinescopeWebhookPassword = '',
    ) {
    }

    #[Route('/webhooks/yookassa', name: 'web_webhooks_yookassa', methods: ['POST'])]
    public function yookassa(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable $exception) {
            $this->logger->warning('YooKassa webhook: invalid JSON.', [
                'exception' => $exception->getMessage(),
            ]);

            return $this->json(['ok' => true], Response::HTTP_OK);
        }

        try {
            $this->paymentOfferService->handleYookassaWebhook($payload);
        } catch (\Throwable $exception) {
            $this->logger->error('YooKassa webhook handling failed.', [
                'exception' => $exception->getMessage(),
            ]);

            return $this->json(['error' => 'Internal error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['ok' => true]);
    }

    #[Route('/webhooks/kinescope', name: 'web_webhooks_kinescope', methods: ['POST'])]
    public function kinescope(Request $request): JsonResponse
    {
        if (!$this->isKinescopeAuthorized($request)) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $payload = $request->toArray();
        } catch (\Throwable $exception) {
            $this->logger->warning('Kinescope webhook: invalid JSON.', [
                'exception' => $exception->getMessage(),
            ]);

            return $this->json(['ok' => true], Response::HTTP_OK);
        }

        try {
            $this->practiceKinescope->handleKinescopeWebhook($payload);
        } catch (\Throwable $exception) {
            $this->logger->error('Kinescope webhook handling failed.', [
                'exception' => $exception->getMessage(),
                'event' => $payload['event'] ?? null,
            ]);

            return $this->json(['error' => 'Internal error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['ok' => true]);
    }

    private function isKinescopeAuthorized(Request $request): bool
    {
        $user = trim($this->kinescopeWebhookUser);
        $password = $this->kinescopeWebhookPassword;

        if ('' === $user && '' === $password) {
            return true;
        }

        $providedUser = (string) $request->server->get('PHP_AUTH_USER', '');
        $providedPass = (string) $request->server->get('PHP_AUTH_PW', '');

        return hash_equals($user, $providedUser) && hash_equals($password, $providedPass);
    }
}
