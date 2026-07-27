<?php

namespace App\Service;

use App\Entity\PushSubscription;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PushSubscriptionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PushSubscriptionRepository $subscriptions,
        private readonly ContentLikeService $likes,
    ) {
    }

    /**
     * @param array{endpoint?: mixed, keys?: mixed} $payload
     * @return array{ok: bool, message: string}
     */
    public function subscribe(array $payload, Request $request, Response $response): array
    {
        $endpoint = trim((string) ($payload['endpoint'] ?? ''));
        $keys = $payload['keys'] ?? null;
        $p256dh = is_array($keys) ? trim((string) ($keys['p256dh'] ?? '')) : '';
        $auth = is_array($keys) ? trim((string) ($keys['auth'] ?? '')) : '';

        if ('' === $endpoint || !filter_var($endpoint, FILTER_VALIDATE_URL)) {
            return ['ok' => false, 'message' => 'Некорректный endpoint подписки.'];
        }
        if ('' === $p256dh || '' === $auth) {
            return ['ok' => false, 'message' => 'Некорректные ключи подписки.'];
        }
        if (mb_strlen($endpoint) > 2048 || mb_strlen($p256dh) > 255 || mb_strlen($auth) > 255) {
            return ['ok' => false, 'message' => 'Слишком длинные данные подписки.'];
        }

        $visitorToken = $this->likes->ensureVisitorToken($request, $response);
        $existing = $this->subscriptions->findOneByEndpoint($endpoint);

        if (null !== $existing) {
            $existing->refreshKeys($p256dh, $auth, $visitorToken);
        } else {
            $this->em->persist(new PushSubscription($endpoint, $p256dh, $auth, $visitorToken));
        }

        $this->em->flush();

        return ['ok' => true, 'message' => 'Подписка на уведомления включена.'];
    }

    /**
     * @param array{endpoint?: mixed} $payload
     * @return array{ok: bool, message: string}
     */
    public function unsubscribe(array $payload): array
    {
        $endpoint = trim((string) ($payload['endpoint'] ?? ''));
        if ('' === $endpoint) {
            return ['ok' => false, 'message' => 'Не указан endpoint.'];
        }

        $existing = $this->subscriptions->findOneByEndpoint($endpoint);
        if (null !== $existing) {
            $this->em->remove($existing);
            $this->em->flush();
        }

        return ['ok' => true, 'message' => 'Подписка отключена.'];
    }
}
