<?php

declare(strict_types=1);

namespace MezzioTest\Session\Persistence;

use DateTimeImmutable;
use Laminas\Diactoros\Response;
use Mezzio\Session\Persistence\CacheHeadersGeneratorTrait;
use Mezzio\Session\Persistence\Http;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;

use function assert;
use function filemtime;
use function getlastmod;
use function gmdate;
use function sprintf;
use function time;

class CacheHeadersGeneratorTraitTest extends TestCase
{
    public const GMDATE_REGEXP = '/^'
        . '(Sun|Mon|Tue|Wed|Thu|Fri|Sat), '
        . '[0-3][0-9] '
        . '(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) '
        . '[0-9]{4} '
        . '[0-2][0-9]:[0-5][0-9]:[0-5][0-9] '
        . 'GMT'
    . '$/';

    protected function setUp(): void
    {
    }

    protected function createConsumerInstance(
        int $cacheExpire = 180,
        string $cacheLimiter = '',
        ?string $lastModified = null
    ): object {
        return new class ($cacheExpire, $cacheLimiter, $lastModified) {
            use CacheHeadersGeneratorTrait {
                addCacheHeadersToResponse as trait_addCacheHeadersToResponse;
                responseAlreadyHasCacheHeaders as trait_responseAlreadyHasCacheHeaders;
                getLastModified as trait_getLastModified;
            }

            public function __construct(int $cacheExpire, string $cacheLimiter, ?string $lastModified = null)
            {
                $this->cacheExpire  = $cacheExpire;
                $this->cacheLimiter = $cacheLimiter;
                $this->lastModified = $lastModified;
            }

            public function addCacheHeadersToResponse(ResponseInterface $response): ResponseInterface
            {
                return $this->trait_addCacheHeadersToResponse($response);
            }

            /** @return string|false */
            public function getLastModified()
            {
                return $this->trait_getLastModified();
            }

            public function responseAlreadyHasCacheHeaders(ResponseInterface $response): bool
            {
                return $this->trait_responseAlreadyHasCacheHeaders($response);
            }
        };
    }

    public function testLastModified(): void
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
    public function testResponseAlreadyHasCacheHeaders(
        string $name,
        string $value,
        bool $expected
    ): void {
        $consumer = $this->createConsumerInstance();

        $response = new Response();
        $response = $response->withHeader($name, $value);

        self::assertSame($expected, $consumer->responseAlreadyHasCacheHeaders($response));

        $response = $response->withoutHeader($name);
        self::assertFalse($consumer->responseAlreadyHasCacheHeaders($response));
    }

    public function provideCacheHeaderValues(): array
    {
        return [
            'expires'       => ['Expires', 'Sat, 14 Apr 1945 00:00:00 GMT', true],
            'last-modified' => ['Last-Modified', 'Sat, 25 Mar 1972 00:00:00 GMT', true],
            'cache-control' => ['Cache-Control', 'private, max-age=3600', true],
            'pragma'        => ['Pragma', 'no-cache', true],
            'other-header'  => ['Content-Language', 'en', false],
        ];
    }

    public function provideUnsupportedCacheLimiters(): array
    {
        return [
            'empty'       => [''],
            'unsupported' => ['unsupported'],
        ];
    }

    /**
     * @dataProvider provideUnsupportedCacheLimiters
     */
    public function testDontAddCacheHeadersForEmptyOrUnsupportedCacheLimiter(string $cacheLimiter): void
    {
        $consumer = $this->createConsumerInstance(60, $cacheLimiter);
        $response = $consumer->addCacheHeadersToResponse(new Response());

        self::assertFalse($response->hasHeader('Expires'));
        self::assertFalse($response->hasHeader('Last-Modified'));
        self::assertFalse($response->hasHeader('Cache-Control'));
        self::assertFalse($response->hasHeader('Pragma'));
    }

    public function providePreexistingCacheHeaders(): iterable
    {
        yield 'last-modified' => [
            'Last-Modified',
            gmdate(Http::DATE_FORMAT),
            [
                'Pragma',
                'Expires',
                'Cache-Control',
            ],
        ];

        yield 'cache-control' => [
            'Cache-Control',
            'public, max-age=3600',
            [
                'Pragma',
                'Expires',
                'Last-Modified',
            ],
        ];

        yield 'pragma' => [
            'Pragma',
            'no-cache',
            [
                'Expires',
                'Cache-Control',
                'Last-Modified',
            ],
        ];

        yield 'expires' => [
            'Expires',
            gmdate(Http::DATE_FORMAT),
            [
                'Pragma',
                'Cache-Control',
                'Last-Modified',
            ],
        ];
    }

    /**
     * @dataProvider providePreexistingCacheHeaders
     * @param string[] $headersThatShouldNotBePresent
     */
    public function testDontAddExtraCacheHeadersIfResponseAlreadyHasAny(
        string $headerName,
        string $headerValue,
        array $headersThatShouldNotBePresent
    ): void {
        $consumer = $this->createConsumerInstance(60, 'public');
        $response = (new Response())->withHeader($headerName, $headerValue);
        $response = $consumer->addCacheHeadersToResponse($response);
        foreach ($headersThatShouldNotBePresent as $header) {
            $this->assertFalse($response->hasHeader($header), sprintf(
                'Response already containing header "%s" should not be injected with header "%s"',
                $headerName,
                $header
            ));
        }
    }

    public function testAddCacheHeadersForNoCacheCacheLimiter(): void
    {
        $consumer = $this->createConsumerInstance(60, 'nocache');
        $response = $consumer->addCacheHeadersToResponse(new Response());

        self::assertSame(Http::CACHE_PAST_DATE, $response->getHeaderLine('Expires'));
        self::assertSame('no-store, no-cache, must-revalidate', $response->getHeaderLine('Cache-Control'));
        self::assertFalse($response->hasHeader('Last-Modified'));
        self::assertSame('no-cache', $response->getHeaderLine('Pragma'));
    }

    public function testAddCacheHeadersForPublicCacheLimiter(): void
    {
        $cacheExpire = 60;
        $maxAge      = 60 * $cacheExpire;

        $consumer = $this->createConsumerInstance($cacheExpire, 'public');
        $response = $consumer->addCacheHeadersToResponse(new Response());

        $lastModified = $this->getExpectedLastModified() ?: '';

        self::assertMatchesRegularExpression(self::GMDATE_REGEXP, $response->getHeaderLine('Expires'));
        self::assertSame(sprintf('public, max-age=%d', $maxAge), $response->getHeaderLine('Cache-Control'));
        self::assertSame($lastModified, $response->getHeaderLine('Last-Modified'));
        self::assertFalse($response->hasHeader('Pragma'));
    }

    public function testAddCacheHeadersForPrivateCacheLimiter(): void
    {
        $cacheExpire = 60;
        $maxAge      = 60 * $cacheExpire;

        $consumer = $this->createConsumerInstance($cacheExpire, 'private');
        $response = $consumer->addCacheHeadersToResponse(new Response());

        $lastModified = $this->getExpectedLastModified() ?: '';

        self::assertMatchesRegularExpression(self::GMDATE_REGEXP, $response->getHeaderLine('Expires'));
        self::assertSame(sprintf('private, max-age=%d', $maxAge), $response->getHeaderLine('Cache-Control'));
        self::assertSame($lastModified, $response->getHeaderLine('Last-Modified'));
        self::assertFalse($response->hasHeader('Pragma'));
    }

    /**
     * @dataProvider provideCacheLimiterValues
     */
    public function testResponseCacheHeadersToResponseWithValidCacheLimiters(
        int $cacheExpire,
        string $cacheLimiter,
        string $expectedExpires,
        string $expectedLastModified,
        string $expectedCacheControl,
        string $expectedPragma
    ): void {
        $consumer = $this->createConsumerInstance($cacheExpire, $cacheLimiter);

        $response = $consumer->addCacheHeadersToResponse(new Response());

        $actualExpires = $response->getHeaderLine('Expires');
        self::assertIsString($actualExpires);
        self::assertEqualDateWithDelta($expectedExpires, $actualExpires, 2);
        self::assertSame($expectedLastModified, $response->getHeaderLine('Last-Modified'));
        self::assertSame($expectedCacheControl, $response->getHeaderLine('Cache-Control'));
        self::assertSame($expectedPragma, $response->getHeaderLine('Pragma'));
    }

    private static function assertEqualDateWithDelta(string $expect, string $actual, int $delta = 2): void
    {
        if ($expect === '') {
            self::assertEquals($expect, $actual);

            return;
        }

        $expectedDate = DateTimeImmutable::createFromFormat(Http::DATE_FORMAT, $expect);
        assert($expectedDate instanceof DateTimeImmutable);
        $actualDate = DateTimeImmutable::createFromFormat(Http::DATE_FORMAT, $actual);
        assert($actualDate instanceof DateTimeImmutable);

        self::assertEqualsWithDelta($expectedDate->getTimestamp(), $actualDate->getTimestamp(), $delta);
    }

    public function provideCacheLimiterValues(): array
    {
        $cacheExpire  = 60;
        $maxAge       = (string) (60 * $cacheExpire);
        $lastModified = $this->getExpectedLastModified();

        return [
            'empty'     => [
                'cache_expire'           => $cacheExpire,
                'cache_limiter'          => '',
                'expected_expires'       => '',
                'expected_last_modified' => '',
                'expected_cache_control' => '',
                'expected_pragma'        => '',
            ],
            'not-valid' => [
                'cache_expire'           => $cacheExpire,
                'cache_limiter'          => 'not-valid',
                'expected_expires'       => '',
                'expected_last_modified' => '',
                'expected_cache_control' => '',
                'expected_pragma'        => '',
            ],
            'nocache'   => [
                'cache_expire'           => $cacheExpire,
                'cache_limiter'          => 'nocache',
                'expected_expires'       => Http::CACHE_PAST_DATE,
                'expected_last_modified' => '',
                'expected_cache_control' => 'no-store, no-cache, must-revalidate',
                'expected_pragma'        => 'no-cache',
            ],
            'public'    => [
                'cache_expire'           => $cacheExpire,
                'cache_limiter'          => 'public',
                'expected_expires'       => gmdate(Http::DATE_FORMAT, time() + (60 * $cacheExpire)),
                'expected_last_modified' => $lastModified,
                'expected_cache_control' => 'public, max-age=' . $maxAge,
                'expected_pragma'        => '',
            ],
            'private'   => [
                'cache_expire'           => $cacheExpire,
                'cache_limiter'          => 'private',
                'expected_expires'       => Http::CACHE_PAST_DATE,
                'expected_last_modified' => $lastModified,
                'expected_cache_control' => 'private, max-age=' . $maxAge,
                'expected_pragma'        => '',
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
            $rc        = new ReflectionClass(CacheHeadersGeneratorTrait::class);
            $classFile = $rc->getFileName();
            $lastmod   = filemtime($classFile);
        }

        return $lastmod ? gmdate(Http::DATE_FORMAT, $lastmod) : false;
    }
}
