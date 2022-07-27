<?php

declare(strict_types=1);

namespace MezzioTest\Session\Persistence;

use Dflydev\FigCookies\SetCookie;
use Mezzio\Session\Persistence\SessionCookieAwareTrait;
use Mezzio\Session\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SessionCookieAwareConsumer
{
    use SessionCookieAwareTrait {
        getSessionCookieValueFromRequest as trait_getSessionCookieValueFromRequest;
        addSessionCookieHeaderToResponse as trait_addSessionCookieHeaderToResponse;
        createSessionCookieForResponse as trait_createSessionCookieForResponse;
        getSessionCookieLifetime as trait_getSessionCookieLifetime;
        isDeleteCookieOnEmptySession as trait_isDeleteCookieOnEmptySession;
    }

    public function __construct(
        string $cookieName,
        int $cookieLifetime = 0,
        string $cookiePath = '/',
        ?string $cookieDomain = null,
        bool $cookieSecure = false,
        bool $cookieHttpOnly = false,
        string $cookieSameSite = '',
        bool $deleteCookieOnEmptySession = false
    ) {
        $this->cookieName                 = $cookieName;
        $this->cookieLifetime             = $cookieLifetime;
        $this->cookiePath                 = $cookiePath;
        $this->cookieDomain               = $cookieDomain;
        $this->cookieSecure               = $cookieSecure;
        $this->cookieHttpOnly             = $cookieHttpOnly;
        $this->cookieSameSite             = $cookieSameSite;
        $this->deleteCookieOnEmptySession = $deleteCookieOnEmptySession;
    }

    public function getSessionCookieValueFromRequest(ServerRequestInterface $request): string
    {
        return $this->trait_getSessionCookieValueFromRequest($request);
    }

    public function addSessionCookieHeaderToResponse(
        ResponseInterface $response,
        string $cookieValue,
        SessionInterface $session
    ): ResponseInterface {
        return $this->trait_addSessionCookieHeaderToResponse($response, $cookieValue, $session);
    }

    public function createSessionCookieForResponse(string $cookieValue, int $cookieLifetime = 0): SetCookie
    {
        return $this->trait_createSessionCookieForResponse($cookieValue, $cookieLifetime);
    }

    public function getSessionCookieLifetime(SessionInterface $session): int
    {
        return $this->trait_getSessionCookieLifetime($session);
    }

    public function isDeleteCookieOnEmptySession(): bool
    {
        /** @psalm-suppress InternalMethod */
        return $this->trait_isDeleteCookieOnEmptySession();
    }
}
