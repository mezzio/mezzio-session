<?php

/**
 * @see       https://github.com/mezzio/mezzio-session for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-session/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-session/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Mezzio\Session\Persistence;

use Dflydev\FigCookies\FigRequestCookies;
use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\SetCookie;
use Dflydev\FigCookies\Modifier\SameSite;
use Mezzio\Session\SessionCookiePersistenceInterface;
use Mezzio\Session\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function class_exists;
use function method_exists;
use function time;

/**
 * Provides methods for retrieving the session-cookie value from the request and
 * decorating the response with a session-cookie header.
 */
trait SessionCookieAwareTrait
{
    /** @var string */
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
    private function getSessionCookieValueFromRequest(ServerRequestInterface $request) : string
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
    ) : ResponseInterface {
        return FigResponseCookies::set(
            $response,
            $this->createSessionCookieForResponse(
                $this->cookieName,
                $cookieValue,
                $this->getSessionCookieLifetime($session)
            )
        );
    }

    /**
     * Build a SetCookie instance for client session persistence.
     *
     * @param string $cookieValue The cookie value
     * @param int|null $cookieLifetime A session cookie lifetime other than the default
     */
    private function createSessionCookieForResponse(string $cookieValue, int $cookieLifetime = 0) : SetCookie
    {
        $sessionCookie = SetCookie::create($this->cookieName)
            ->withValue($cookieValue)
            ->withPath($this->cookiePath)
            ->withDomain($this->cookieDomain)
            ->withSecure($this->cookieSecure)
            ->withHttpOnly($this->cookieHttpOnly);

        if ($this->cookieSameSite
            && method_exists($sessionCookie, 'withSameSite')
            && class_exists(SameSite::class)
        ) {
            $sessionCookie = $sessionCookie->withSameSite(
                SameSite::fromString($this->cookieSameSite)
            );
        }

        return $cookieLifetime
            ? $sessionCookie->withExpires(time() + $cookieLifetime)
            : $sessionCookie;
    }

    private function getSessionCookieLifetime(SessionInterface $session) : int
    {
        $lifetime = (int) $this->cookieLifetime;
        if ($session instanceof SessionCookiePersistenceInterface
            && $session->has(SessionCookiePersistenceInterface::SESSION_LIFETIME_KEY)
        ) {
            $lifetime = $session->getSessionLifetime();
        }

        return $lifetime > 0 ? $lifetime : 0;
    }
}
