<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

/**
 * Programmatic login for SiteUser (email code / Telegram).
 * Not used for HTTP request authentication — only via Security::login().
 */
final class SiteUserAuthenticator extends AbstractAuthenticator
{
    public function supports(Request $request): ?bool
    {
        // Never handles HTTP requests. Used only via Security::login().
        // Important: return false, not null — null means "lazy" and still calls authenticate().
        return false;
    }

    public function authenticate(Request $request): Passport
    {
        throw new \LogicException('SiteUserAuthenticator is only used via Security::login().');
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null;
    }
}
