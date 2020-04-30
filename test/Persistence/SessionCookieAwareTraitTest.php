<?php

/**
 * @see       https://github.com/laminas/laminas-diactoros for the canonical source repository
 * @copyright https://github.com/laminas/laminas-diactoros/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-diactoros/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace MezzioTest\Session\Persistence;

use Dflydev\FigCookies\Cookie;
use Dflydev\FigCookies\FigRequestCookies;
use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\Modifier\SameSite;
use Dflydev\FigCookies\SetCookie;
use Dflydev\FigCookies\SetCookies;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequestFactory;
use Mezzio\Session\Persistence\SessionCookieAwareTrait;
use Mezzio\Session\Session;
use Mezzio\Session\SessionInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class SessionCookieAwareTraitTest extends TestCase
{
    const EXPIRE_REGEXP = '/'
        . 'Expires\='
        . '(Sun|Mon|Tue|Wed|Thu|Fri|Sun), '
        . '[0-3][0-9] '
        . '(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) '
        . '[0-9]{4} '
        . '[0-2][0-9]:[0-5][0-9]:[0-5][0-9] '
        . 'GMT'
    . '/i';

    private const COOKIE_NAME = 'SESSIONCOOKIENAME';
    private const COOKIE_LIFETIME = 0;
    private const COOKIE_PATH = '/';
    private const COOKIE_DOMAIN = null;
    private const COOKIE_SECURE = false;
    private const COOKIE_HTTPONLY = false;
    private const COOKIE_SAMESITE = '';

    protected function setUp() : void
    {
    }

    protected function createConsumerInstance(
        string $cookieName = null,
        int $cookieLifetime = null,
        string $cookiePath = null,
        string $cookieDomain = null,
        bool $cookieSecure = null,
        bool $cookieHttpOnly = null,
        string $cookieSameSite = null
    ) {
        return new class(
            $cookieName ?? self::COOKIE_NAME,
            $cookieLifetime ?? self::COOKIE_LIFETIME,
            $cookiePath ?? self::COOKIE_PATH,
            $cookieDomain ?? self::COOKIE_DOMAIN,
            $cookieSecure ?? self::COOKIE_SECURE,
            $cookieHttpOnly ?? self::COOKIE_HTTPONLY,
            $cookieSameSite ?? self::COOKIE_SAMESITE
        ) {
            use SessionCookieAwareTrait {
                getSessionCookieValueFromRequest as trait_getSessionCookieValueFromRequest;
                addSessionCookieHeaderToResponse as trait_addSessionCookieHeaderToResponse;
                createSessionCookieForResponse as trait_createSessionCookieForResponse;
                getSessionCookieLifetime as trait_getSessionCookieLifetime;
            }

            public function __construct(
                string $cookieName,
                int $cookieLifetime = 0,
                string $cookiePath = '/',
                string $cookieDomain = null,
                bool $cookieSecure = false,
                bool $cookieHttpOnly = false,
                string $cookieSameSite = ''
            ) {
                $this->cookieName     = $cookieName;
                $this->cookieLifetime = $cookieLifetime;
                $this->cookiePath     = $cookiePath;
                $this->cookieDomain   = $cookieDomain;
                $this->cookieSecure   = $cookieSecure;
                $this->cookieHttpOnly = $cookieHttpOnly;
                $this->cookieSameSite = $cookieSameSite;
            }

            public function getSessionCookieValueFromRequest(ServerRequestInterface $request) : string
            {
                return $this->trait_getSessionCookieValueFromRequest($request);
            }

            public function addSessionCookieHeaderToResponse(
                ResponseInterface $response,
                string $cookieValue,
                SessionInterface $session
            ) : ResponseInterface {
                return $this->trait_addSessionCookieHeaderToResponse($response, $cookieValue, $session);
            }

            public function createSessionCookieForResponse(string $cookieValue, int $cookieLifetime = 0) : SetCookie
            {
                return $this->trait_createSessionCookieForResponse($cookieValue, $cookieLifetime);
            }

            public function getSessionCookieLifetime(SessionInterface $session) : int
            {
                return $this->trait_getSessionCookieLifetime($session);
            }
        };
    }

    /**
     * @dataProvider provideRequestSessionCookieValues
     */
    public function testGetSessionCookieValueFromRequestUsingInjectesCookieParams(
        string $cookieName,
        string $cookieValue = null,
        string $expected = null
    ) {
        $consumer = $this->createConsumerInstance($cookieName);

        $cookies = [$cookieName => $cookieValue];
        $request = ServerRequestFactory::fromGlobals([], [], [], $cookies, []);

        self::assertSame($expected, $consumer->getSessionCookieValueFromRequest($request));
    }

    /**
     * @dataProvider provideRequestSessionCookieValues
     */
    public function testGetSessionCookieValueFromRequestUsingInjectedCookieHeader(
        string $cookieName,
        string $cookieValue = null,
        string $expected = null
    ) {
        $consumer = $this->createConsumerInstance($cookieName);

        $cookie = Cookie::create($cookieName, $cookieValue);
        $request = FigRequestCookies::set(new ServerRequest(), $cookie);

        self::assertSame($expected, $consumer->getSessionCookieValueFromRequest($request));
    }

    public function provideRequestSessionCookieValues()
    {
        $cookieName  = 'SESSIONID';
        $cookieNiceValue = 'some-nice-value';
        $cookieWeirdValue = '!"£$%&/';

        return [
            [$cookieName, null, ''],
            [$cookieName, $cookieNiceValue, $cookieNiceValue],
            [$cookieName, $cookieWeirdValue, $cookieWeirdValue],
        ];
    }

    public function testAddSessionCookieHeaderToResponse()
    {
        $cookieName  = 'ADDSESSIONCOOKIEID';
        $cookieValue = 'set-some-cookie-value';
        $sessionLifetime = 3600;

        $consumer = $this->createConsumerInstance($cookieName);

        $session = new Session([]);
        $session->persistSessionFor($sessionLifetime);
        $response = $consumer->addSessionCookieHeaderToResponse(new Response(), $cookieValue, $session);

        $actualHeaderLine = $response->getHeaderLine('Set-Cookie');

        self::assertStringStartsWith(sprintf('%s=%s; Path=/; Expires=', $cookieName, $cookieValue), $actualHeaderLine);
        self::assertRegExp(self::EXPIRE_REGEXP, $actualHeaderLine);
        self::assertStringEndsWith(sprintf('GMT; Max-Age=%d', $sessionLifetime), $actualHeaderLine);
    }

    /**
     * @dataProvider provideResponseCookieHeaderLines
     */
    public function testCreateSessionCookieForResponse(
        string $cookieName,
        ?string $cookieValue,
        int $cookieLifetime,
        string $expectedHeaderLine
    ) {
        $consumer = $this->createConsumerInstance($cookieName);
        $setCookie = $consumer->createSessionCookieForResponse(
            $cookieValue ?? '',
            $cookieLifetime ?? 0
        );

        $actualHeaderLine = (string) $setCookie;

        self::assertSame($expectedHeaderLine, $actualHeaderLine);
    }

    public function provideResponseCookieHeaderLines()
    {
        $cookieName = 'PHPSESSID';

        $cookieNiceValue = 'some-nice-value';
        $cookieWeirdValue = '!"£$%&/';

        return [
            [$cookieName, null, 0, sprintf('%s=; Path=/', $cookieName)],
            [$cookieName, $cookieNiceValue, 0, sprintf('%s=%s; Path=/', $cookieName, $cookieNiceValue)],
            [$cookieName, $cookieWeirdValue, 0, sprintf('%s=%s; Path=/', $cookieName, urlencode($cookieWeirdValue))],
        ];
    }

    public function testCreateSessionCookieForResponseWithExpires()
    {
        $cookieName  = 'PHPSESSID';
        $cookieValue = 'a-cookie-value';
        $cookieLifetime = 3600;

        $consumer = $this->createConsumerInstance($cookieName);
        $setCookie = $consumer->createSessionCookieForResponse($cookieValue, $cookieLifetime);

        $actual = (string) $setCookie;

        self::assertStringStartsWith(sprintf('%s=%s; Path=/; ', $cookieName, $cookieValue), $actual);
        self::assertRegExp(self::EXPIRE_REGEXP, $actual);
    }

    public function testCreateSessionCookieForResponseWithDomain()
    {
        $cookieName  = 'PHPSESSID';
        $cookieValue = 'a-cookie-value';
        $cookieDomain = 'example.com';

        $consumer = $this->createConsumerInstance($cookieName, null, null, $cookieDomain);
        $setCookie = $consumer->createSessionCookieForResponse($cookieValue);

        $expected = sprintf('%s=%s; Domain=%s; Path=/', $cookieName, $cookieValue, $cookieDomain);
        $actual = (string) $setCookie;

        self::assertSame($expected, $actual);
    }

    public function testCreateSessionCookieForResponseWithSecure()
    {
        $cookieName  = 'PHPSESSID';
        $cookieValue = 'a-cookie-value';

        $consumer = $this->createConsumerInstance($cookieName, null, null, null, true);
        $setCookie = $consumer->createSessionCookieForResponse($cookieValue);

        $expected = sprintf('%s=%s; Path=/; Secure', $cookieName, $cookieValue);
        $actual = (string) $setCookie;

        self::assertSame($expected, $actual);
    }

    public function testCreateSessionCookieForResponseWithHttpOnly()
    {
        $cookieName  = 'PHPSESSID';
        $cookieValue = 'a-cookie-value';

        $consumer = $this->createConsumerInstance($cookieName, null, null, null, null, true);
        $setCookie = $consumer->createSessionCookieForResponse($cookieValue);

        $expected = sprintf('%s=%s; Path=/; HttpOnly', $cookieName, $cookieValue);
        $actual = (string) $setCookie;

        self::assertSame($expected, $actual);
    }

    public function testCreateSessionCookieForResponseWithSameSiteIfSupported()
    {
        $cookieName  = 'PHPSESSID';
        $cookieValue = 'a-cookie-value';
        $cookieSameSite = 'Lax';

        $consumer = $this->createConsumerInstance($cookieName, null, null, null, null, null, $cookieSameSite);
        $setCookie = $consumer->createSessionCookieForResponse($cookieValue);

        if (class_exists(SameSite::class)
            && method_exists($setCookie, 'withSameSite')
        ) {
            $expected = sprintf('%s=%s; Path=/; SameSite=%s', $cookieName, $cookieValue, $cookieSameSite);
        } else {
            $expected = sprintf('%s=%s; Path=/', $cookieName, $cookieValue);
        }
        $actual = (string) $setCookie;

        self::assertSame($expected, $actual);
    }

    /**
     * @dataProvider provideSessionCookieLifetimeValues
     */
    public function testGetSessionCookieLifetimeReturnsExpectedResults(
        int $cookieLifetime = null,
        int $sessionLifetime = null,
        int $expected = null
    ) {
        $consumer = $this->createConsumerInstance('SESSIONCOOKIENAME', $cookieLifetime ?? 0);
        $session = new Session([]);
        if (isset($sessionLifetime)) {
            $session->persistSessionFor($sessionLifetime);
        }

        self::assertSame($expected, $consumer->getSessionCookieLifetime($session));
    }

    public function provideSessionCookieLifetimeValues()
    {
        return [
            'default' => [null, null, 0],
            'cookie=0|session=null'   => [0, null, 0],
            'cookie=0|session=0'      => [0, 0, 0],
            'cookie=-1|session=null'   => [-1, null, 0],
            'cookie=-1|session=0'     => [-1, 0, 0],
            'cookie=-1|session=-1'    => [-1, -1, 0],
            'cookie=+60|session=null' => [60, null, 60],
            'cookie=+60|session=0'    => [60, 0, 0],
            'cookie=+60|session=-1'   => [60, -1, 0],
            'cookie=+60|session=30'   => [60, 30, 30],
            'cookie=null|session=0'   => [null, 0, 0],
            'cookie=null|session=-1'   => [null, -1, 0],
            'cookie=null|session=30'   => [null, 30, 30],
        ];
    }
}
