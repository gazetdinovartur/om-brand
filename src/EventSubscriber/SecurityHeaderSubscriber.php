<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SecurityHeaderSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onResponse', -10],
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = (string) $event->getRequest()->attributes->get('_route');
        if (str_starts_with($route, 'admin') || str_starts_with($route, '_')) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        $headers->set(
            'Content-Security-Policy',
            "default-src 'self'; "
            // Telegram Login Widget: script + oauth iframe on /library/login
            ."script-src 'self' 'unsafe-inline' https://music.arturlun.ru https://telegram.org; "
            ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
            ."img-src 'self' data: blob: https://i.ytimg.com https://img.youtube.com https://t.me https://telegram.org; "
            ."font-src 'self' https://fonts.gstatic.com; "
            ."media-src 'self' https://music.arturlun.ru blob:; "
            ."frame-src 'self' https://www.youtube-nocookie.com https://www.youtube.com https://player.vimeo.com https://rutube.ru https://oauth.telegram.org https://telegram.org; "
            ."connect-src 'self' https://music.arturlun.ru https://telegram.org https://oauth.telegram.org; "
            ."base-uri 'self'; "
            ."form-action 'self'",
        );
    }
}
