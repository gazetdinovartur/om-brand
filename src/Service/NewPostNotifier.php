<?php

namespace App\Service;

use App\Entity\ChronicleEntry;
use App\Enum\EmailSubscriberStatus;
use App\Repository\EmailSubscriberRepository;
use App\Repository\PushSubscriptionRepository;
use App\Repository\SiteSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\ContentEncoding;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class NewPostNotifier
{
    public function __construct(
        private readonly EmailSubscriberRepository $emailSubscribers,
        private readonly PushSubscriptionRepository $pushSubscriptions,
        private readonly EmailSubscriptionService $emailSubscription,
        private readonly SiteSettingsRepository $settingsRepository,
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(default::VAPID_PUBLIC_KEY)%')]
        private readonly string $vapidPublicKey = '',
        #[Autowire('%env(default::VAPID_PRIVATE_KEY)%')]
        private readonly string $vapidPrivateKey = '',
        #[Autowire('%env(default::VAPID_SUBJECT)%')]
        private readonly string $vapidSubject = '',
        #[Autowire('%env(default:app.site_url:APP_SITE_URL)%')]
        private readonly string $siteUrl = '',
    ) {
    }

    public function isPushConfigured(): bool
    {
        return '' !== trim($this->vapidPublicKey)
            && '' !== trim($this->vapidPrivateKey)
            && '' !== trim($this->vapidSubject);
    }

    public function notifyPublished(ChronicleEntry $entry): void
    {
        if (!$entry->isVisibleInFeed()) {
            return;
        }

        $this->notifyEmail($entry);
        $this->notifyPush($entry);
    }

    private function notifyEmail(ChronicleEntry $entry): void
    {
        if (!$this->emailSubscription->isMailConfigured()) {
            return;
        }

        $subscribers = $this->emailSubscribers->findConfirmed();
        if ([] === $subscribers) {
            return;
        }

        $settings = $this->settingsRepository->getSettings();
        $siteName = trim((string) $settings->getName()) ?: $this->siteDomain();
        $postUrl = $this->absolutePostUrl($entry);
        $lede = trim((string) ($entry->getLede() ?? ''));

        foreach ($subscribers as $subscriber) {
            if (EmailSubscriberStatus::Confirmed !== $subscriber->getStatus()) {
                continue;
            }

            $unsubscribeUrl = $this->urlGenerator->generate(
                'web_email_subscribe_unsubscribe',
                ['token' => $subscriber->getUnsubscribeToken()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $this->emailSubscription->sendTemplated(
                $settings,
                $subscriber->getEmail(),
                sprintf('Новая запись: %s', $entry->getTitle()),
                'emails/new_post.html.twig',
                'emails/new_post.txt.twig',
                [
                    'entry' => $entry,
                    'postUrl' => $postUrl,
                    'lede' => $lede,
                    'siteName' => $siteName,
                    'siteDomain' => $this->siteDomain(),
                    'unsubscribeUrl' => $unsubscribeUrl,
                ],
            );
        }
    }

    private function notifyPush(ChronicleEntry $entry): void
    {
        if (!$this->isPushConfigured()) {
            return;
        }

        $subscriptions = $this->pushSubscriptions->findAllSubscriptions();
        if ([] === $subscriptions) {
            return;
        }

        $postUrl = $this->absolutePostUrl($entry);
        $payload = json_encode([
            'title' => $entry->getTitle(),
            'body' => $this->pushBody($entry),
            'url' => $postUrl,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        try {
            $webPush = new WebPush(
                [
                    'VAPID' => [
                        'subject' => $this->vapidSubject,
                        'publicKey' => $this->vapidPublicKey,
                        'privateKey' => $this->vapidPrivateKey,
                    ],
                ],
                [],
                null,
                null,
                null,
                null,
                $this->logger,
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to init WebPush.', [
                'exception' => $exception->getMessage(),
            ]);

            return;
        }

        $removed = 0;
        foreach ($subscriptions as $row) {
            $subscription = Subscription::create([
                'endpoint' => $row->getEndpoint(),
                'publicKey' => $row->getP256dh(),
                'authToken' => $row->getAuth(),
                'contentEncoding' => ContentEncoding::aes128gcm->value,
            ]);

            try {
                $report = $webPush->sendOneNotification($subscription, $payload);
            } catch (\Throwable $exception) {
                $this->logger->warning('Web Push send failed.', [
                    'endpoint' => $row->getEndpoint(),
                    'exception' => $exception->getMessage(),
                ]);
                continue;
            }

            if ($report->isSubscriptionExpired()) {
                $this->em->remove($row);
                ++$removed;
                continue;
            }

            if ($report->isSuccess()) {
                $row->touch();
            } else {
                $this->logger->info('Web Push rejected.', [
                    'endpoint' => $row->getEndpoint(),
                    'reason' => $report->getReason(),
                ]);
            }
        }

        if ($removed > 0 || [] !== $subscriptions) {
            $this->em->flush();
        }
    }

    private function absolutePostUrl(ChronicleEntry $entry): string
    {
        return $this->urlGenerator->generate(
            'web_chronicle_show',
            ['slug' => $entry->getSlug()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    private function pushBody(ChronicleEntry $entry): string
    {
        $lede = trim((string) ($entry->getLede() ?? ''));
        if ('' === $lede) {
            return 'Новая запись в хронике';
        }

        if (mb_strlen($lede) > 120) {
            return mb_substr($lede, 0, 117).'…';
        }

        return $lede;
    }

    private function siteDomain(): string
    {
        if ('' !== $this->siteUrl) {
            $host = parse_url($this->siteUrl, PHP_URL_HOST);
            if (is_string($host) && '' !== $host) {
                return $host;
            }
        }

        return 'сайт';
    }
}
