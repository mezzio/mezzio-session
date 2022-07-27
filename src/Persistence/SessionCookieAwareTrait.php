<?php

declare(strict_types=1);

namespace Mezzio\Session\Persistence;

use Dflydev\FigCookies\FigRequestCookies;
use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\Modifier\SameSite;
use Dflydev\FigCookies\SetCookie;
use Mezzio\Session\SessionCookiePersistenceInterface;
use Mezzio\Session\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function time;

/**
 * Provides methods for retrieving the session-cookie value from the request and
 * decorating the response with a session-cookie header.
 */
trait SessionCookieAwareTrait
{
    /** @var string|null */
    private $cookieName;

    /** @var int */
    private $cookieLifetime = 0;

    /** @var string */
    private $cookiePath = '/';

    /** @var string|null */
    private $cookieDomain;

    /** @var bool */
    private $cookieSecure = false;

    /** @var bool */
    private $cookieHttpOnly = false;

    /** @var string */
    private $cookieSameSite = '';

    /**
     * Delete cookie from browser when session becomes empty?
     *
     * @var bool
     */
    private $deleteCookieOnEmptySession = false;

    /**
     * Retrieve the session cookie value.
     *
     * Cookie headers may or may not be present, based on SAPI.  For instance,
     * under Swoole, they are omitted, but the cookie parameters are present.
     * As such, this method uses FigRequestCookies to retrieve the cookie value
     * only if the Cookie header is present. Otherwise, it falls back to the
     * request cookie parameters.
     *
     * In each case, if the value is not found, it falls back to generating a
     * new session identifier.
     */
    private function getSessionCookieValueFromRequest(ServerRequestInterface $request): string
    {
        if ('' === $request->getHeaderLine('Cookie')) {
            return $request->getCookieParams()[$this->cookieName] ?? '';
        }

        return FigRequestCookies::get($request, $this->cookieName)->getValue() ?? '';
    }

    /**
     * Return a cloned response decorated with the session-cookie.
     *
     * @param string $cookieValue The session-cookie value, tipically the session id
     */
    private function addSessionCookieHeaderToResponse(
        ResponseInterface $response,
        string $cookieValue,
        SessionInterface $session
    ): ResponseInterface {
        return FigResponseCookies::set(
            $response,
            $this->createSessionCookieForResponse(
                $cookieValue,
                $this->getSessionCookieLifetime($session)
            )
        );
    }

    /**
     * Build a SetCookie instance for client session persistence.
     *
     * @param string $cookieValue The cookie value
     * @param int    $cookieLifetime A session cookie lifetime other than the default
     */
    private function createSessionCookieForResponse(string $cookieValue, int $cookieLifetime = 0): SetCookie
    {
        $sessionCookie = SetCookie::create($this->cookieName)
            ->withValue($cookieValue)
            ->withPath($this->cookiePath)
            ->withDomain($this->cookieDomain)
            ->withSecure($this->cookieSecure)
            ->withHttpOnly($this->cookieHttpOnly);

        if ($this->cookieSameSite) {
            $sessionCookie = $sessionCookie->withSameSite(
                SameSite::fromString($this->cookieSameSite)
            );
        }

        if ($cookieLifetime) {
            $sessionCookie = $sessionCookie
                ->withExpires(time() + $cookieLifetime)
                ->withMaxAge($cookieLifetime);
        }

        return $sessionCookie;
    }

    private function getSessionCookieLifetime(SessionInterface $session): int
    {
        if ($this->deleteCookieOnEmptySession && ! $session->toArray()) {
            return -(time() - 1);
        }

        $lifetime = (int) $this->cookieLifetime;
        if (
            $session instanceof SessionCookiePersistenceInterface
            && $session->has(SessionCookiePersistenceInterface::SESSION_LIFETIME_KEY)
        ) {
            $lifetime = $session->getSessionLifetime();
        }

        return $lifetime > 0 ? $lifetime : 0;
    }

    /**
     * @internal
     *
     * @return bool whether we delete cookie from browser when session becomes empty
     */
    public function isDeleteCookieOnEmptySession(): bool
    {
        return $this->deleteCookieOnEmptySession;
    }
}
