<?php

/**
 * @see       https://github.com/laminas/laminas-diactoros for the canonical source repository
 * @copyright https://github.com/laminas/laminas-diactoros/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-diactoros/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace MezzioTest\Session\Persistence;

use Laminas\Diactoros\Response;
use Mezzio\Session\Persistence\CacheHeadersGeneratorTrait;
use Mezzio\Session\Persistence\Http;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;

class CacheHeadersGeneratorTraitTest extends TestCase
{
    const GMDATE_REGEXP = '/^'
        . '(Sun|Mon|Tue|Wed|Thu|Fri|Sun), '
        . '[0-3][0-9] '
        . '(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) '
        . '[0-9]{4} '
        . '[0-2][0-9]:[0-5][0-9]:[0-5][0-9] '
        . 'GMT'
    . '$/';

    protected function setUp() : void
    {
    }

    protected function createConsumerInstance(
        int $cacheExpire = 180,
        string $cacheLimiter = '',
        string $lastModified = null
    ) {
        return new class($cacheExpire, $cacheLimiter, $lastModified) {

            use CacheHeadersGeneratorTrait {
                addCacheHeadersToResponse as trait_addCacheHeadersToResponse;
                responseAlreadyHasCacheHeaders as trait_responseAlreadyHasCacheHeaders;
                getLastModified as trait_getLastModified;
            }

            public function __construct(int $cacheExpire, string $cacheLimiter, string $lastModified = null)
            {
                $this->cacheExpire  = $cacheExpire;
                $this->cacheLimiter = $cacheLimiter;
                $this->lastModified = $lastModified;
            }

            public function addCacheHeadersToResponse(ResponseInterface $response) : ResponseInterface
            {
                return $this->trait_addCacheHeadersToResponse($response);
            }

            public function getLastModified()
            {
                return $this->trait_getLastModified();
            }

            public function responseAlreadyHasCacheHeaders(ResponseInterface $response) : bool
            {
                return $this->trait_responseAlreadyHasCacheHeaders($response);
            }
        };
    }

    public function testLastModified()
    {
        // test autodiscover lastModified value
        $consumer = $this->createConsumerInstance();
        self::assertSame($this->getExpectedLastModified(), $consumer->getLastModified());

        // test injected lastModified value
        $consumer = $this->createConsumerInstance(60, '', Http::UNIX_EPOCH);
        self::assertSame(Http::UNIX_EPOCH, $consumer->getLastModified());
    }

    /**
     * @dataProvider provideCacheHeaderValues
     */
    public function testResponseAlreadyHasCacheHeaders($name, $value, $expected)
    {
        $consumer = $this->createConsumerInstance();

        $response = new Response();
        $response = $response->withHeader($name, $value);

        self::assertSame($expected, $consumer->responseAlreadyHasCacheHeaders($response));

        $response = $response->withoutHeader($name);
        self::assertFalse($consumer->responseAlreadyHasCacheHeaders($response));
    }

    public function provideCacheHeaderValues()
    {
        return [
            'expires'       => [ 'Expires', 'Sat, 14 Apr 1945 00:00:00 GMT', true],
            'last-modified' => [ 'Last-Modified', 'Sat, 25 Mar 1972 00:00:00 GMT', true],
            'cache-control' => [ 'Cache-Control', 'private, max-age=3600', true],
            'pragma'        => [ 'Pragma', 'no-cache', true],
            'other-header'  => [ 'Content-Language', 'en', false],
        ];
    }

    public function testDontAddCacheHeadersForEmptyOrUnsupportedCacheLimiter()
    {
        // do not add dataProvider for 2 cases
        $cacheLimuiters = ['', 'unsupported'];

        foreach ($cacheLimuiters as $cacheLimiter) {
            $consumer = $this->createConsumerInstance(60, $cacheLimiter);
            $response = $consumer->addCacheHeadersToResponse(new Response());

            self::assertFalse($response->hasHeader('Expires'));
            self::assertFalse($response->hasHeader('Last-Modified'));
            self::assertFalse($response->hasHeader('Cache-Control'));
            self::assertFalse($response->hasHeader('Pragma'));
        }
    }

    public function testDontAddExtraCacheHeadersIfRespnseAlreadyHasAny()
    {
        $consumer = $this->createConsumerInstance(60, 'public');

        // already has Last-Modified
        $response = (new Response)->withHeader('Last-Modified', gmdate(Http::DATE_FORMAT));
        $response = $consumer->addCacheHeadersToResponse($response);
        $this->assertFalse($response->hasHeader('Pragma'));
        $this->assertFalse($response->hasHeader('Expires'));
        $this->assertFalse($response->hasHeader('Cache-Control'));

        // already has Cache-Control
        $response = (new Response)->withHeader('Cache-Control', 'public, max-age=3600');
        $response = $consumer->addCacheHeadersToResponse($response);
        $this->assertFalse($response->hasHeader('Pragma'));
        $this->assertFalse($response->hasHeader('Expires'));
        $this->assertFalse($response->hasHeader('Last-Modified'));

        // already has Pragma
        $response = (new Response)->withHeader('Pragma', 'no-cache');
        $response = $consumer->addCacheHeadersToResponse($response);
        $this->assertFalse($response->hasHeader('Expires'));
        $this->assertFalse($response->hasHeader('Cache-Control'));
        $this->assertFalse($response->hasHeader('Last-Modified'));

        // already has Expires
        $response = (new Response)->withHeader('Expires', gmdate(Http::DATE_FORMAT));
        $response = $consumer->addCacheHeadersToResponse($response);
        $this->assertFalse($response->hasHeader('Pragma'));
        $this->assertFalse($response->hasHeader('Cache-Control'));
        $this->assertFalse($response->hasHeader('Last-Modified'));
    }

    public function testAddCacheHeadersForNoCacheCacheLimiter()
    {
        $consumer = $this->createConsumerInstance(60, 'nocache');
        $response = $consumer->addCacheHeadersToResponse(new Response());

        self::assertSame(Http::CACHE_PAST_DATE, $response->getHeaderLine('Expires'));
        self::assertSame('no-store, no-cache, must-revalidate', $response->getHeaderLine('Cache-Control'));
        self::assertFalse($response->hasHeader('Last-Modified'));
        self::assertSame('no-cache', $response->getHeaderLine('Pragma'));
    }

    public function testAddCacheHeadersForPublicCacheLimiter()
    {
        $cacheExpire = 60;
        $maxAge      = 60 * $cacheExpire;

        $consumer = $this->createConsumerInstance($cacheExpire, 'public');
        $response = $consumer->addCacheHeadersToResponse(new Response());

        $lastModified = $this->getExpectedLastModified() ?: '';

        self::assertRegExp(self::GMDATE_REGEXP, $response->getHeaderLine('Expires'));
        self::assertSame(sprintf('public, max-age=%d', $maxAge), $response->getHeaderLine('Cache-Control'));
        self::assertSame($lastModified, $response->getHeaderLine('Last-Modified'));
        self::assertFalse($response->hasHeader('Pragma'));
    }

    public function testAddCacheHeadersForPrivateCacheLimiter()
    {
        $cacheExpire = 60;
        $maxAge      = 60 * $cacheExpire;

        $consumer = $this->createConsumerInstance($cacheExpire, 'private');
        $response = $consumer->addCacheHeadersToResponse(new Response());

        $lastModified = $this->getExpectedLastModified() ?: '';

        self::assertRegExp(self::GMDATE_REGEXP, $response->getHeaderLine('Expires'));
        self::assertSame(sprintf('private, max-age=%d', $maxAge), $response->getHeaderLine('Cache-Control'));
        self::assertSame($lastModified, $response->getHeaderLine('Last-Modified'));
        self::assertFalse($response->hasHeader('Pragma'));
    }

//    /**
//     * @dataProvider provideCacheLimiterValues
//     */
//    public function testResponseCacheHeadersToResponseWithValidCacheLimiters(
//        $cacheExpire,
//        $cacheLimiter,
//        $expected_expires,
//        $expected_lastModified,
//        $expected_cacheControl,
//        $expected_pragma
//    ) {
//        $consumer = $this->createConsumerInstance($cacheExpire, $cacheLimiter);
//
//        $response = $consumer->addCacheHeadersToResponse(new Response());
//
//        self::assertSame($expected_expires,      $response->getHeaderLine('Expires'));
//        self::assertSame($expected_lastModified, $response->getHeaderLine('Last-Modified'));
//        self::assertSame($expected_cacheControl, $response->getHeaderLine('Cache-Control'));
//        self::assertSame($expected_pragma,       $response->getHeaderLine('Pragma'));
//    }

    public function provideCacheLimiterValues()
    {
        $cacheExpire  = 60;
        $maxAge       = (string) (60 * $cacheExpire);
        $lastModified = $this->getLastModified();

        return [
            'empty' => [
                'cache_expire' => $cacheExpire,
                'cache_limiter' => '',
                'expected_expires' => '',
                'expected_last_modified' => '',
                'expected_cache_control' => '',
                'expected_pragma' => '',
            ],
            'not-valid' => [
                'cache_expire' => $cacheExpire,
                'cache_limiter' => 'not-valid',
                'expected_expires' => '',
                'expected_last_modified' => '',
                'expected_cache_control' => '',
                'expected_pragma' => '',
            ],
            'nocache' => [
                'cache_expire' => $cacheExpire,
                'cache_limiter' => 'nocache',
                'expected_expires' => Http::CACHE_PAST_DATE,
                'expected_last_modified' => '',
                'expected_cache_control' => 'no-store, no-cache, must-revalidate',
                'expected_pragma' => 'no-cache',
            ],
            'public' => [
                'cache_expire' => $cacheExpire,
                'cache_limiter' => 'public',
                'expected_expires' => Http::CACHE_PAST_DATE,
                'expected_last_modified' => '',
                'expected_cache_control' => 'public, max-age=' . $maxAge,
                'expected_pragma' => '',
            ],
            'private' => [
                'cache_expire' => $cacheExpire,
                'cache_limiter' => 'private',
                'expected_expires' => Http::CACHE_PAST_DATE,
                'expected_last_modified' => '',
                'expected_cache_control' => 'private, max-age=' . $maxAge,
                'expected_pragma' => '',
            ],
        ];
    }

    /**
     * @return string|false
     */
    private function getExpectedLastModified()
    {
        $lastmod = getlastmod();
        if ($lastmod === false) {
            $rc = new ReflectionClass(CacheHeadersGeneratorTrait::class);
            $classFile = $rc->getFileName();
            $lastmod = filemtime($classFile);
        }

        return $lastmod ? gmdate(Http::DATE_FORMAT, $lastmod) : false;
    }
}
