<?php

declare(strict_types=1);

namespace MezzioTest\Session\Persistence;

use DateTimeImmutable;
use Generator;
use Laminas\Diactoros\Response;
use Mezzio\Session\Persistence\CacheHeadersGeneratorTrait;
use Mezzio\Session\Persistence\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
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
    ): CacheHeadersGeneratorConsumer {
        return new CacheHeadersGeneratorConsumer($cacheExpire, $cacheLimiter, $lastModified);
    }

    public function testLastModified(): void
    {
        // test autodiscover lastModified value
        $consumer = $this->createConsumerInstance();
        self::assertSame(self::getExpectedLastModified(), $consumer->getLastModified());

        // test injected lastModified value
        $consumer = $this->createConsumerInstance(60, '', Http::UNIX_EPOCH);
        self::assertSame(Http::UNIX_EPOCH, $consumer->getLastModified());
    }

    #[DataProvider('provideCacheHeaderValues')]
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

    /** @return array<string, array{0: string, 1: string, 2: bool}> */
    public static function provideCacheHeaderValues(): array
    {
        return [
            'expires'       => ['Expires', 'Sat, 14 Apr 1945 00:00:00 GMT', true],
            'last-modified' => ['Last-Modified', 'Sat, 25 Mar 1972 00:00:00 GMT', true],
            'cache-control' => ['Cache-Control', 'private, max-age=3600', true],
            'pragma'        => ['Pragma', 'no-cache', true],
            'other-header'  => ['Content-Language', 'en', false],
        ];
    }

    /** @return array<string, array{0: string}> */
    public static function provideUnsupportedCacheLimiters(): array
    {
        return [
            'empty'       => [''],
            'unsupported' => ['unsupported'],
        ];
    }

    #[DataProvider('provideUnsupportedCacheLimiters')]
    public function testDontAddCacheHeadersForEmptyOrUnsupportedCacheLimiter(string $cacheLimiter): void
    {
        $consumer = $this->createConsumerInstance(60, $cacheLimiter);
        $response = $consumer->addCacheHeadersToResponse(new Response());

        self::assertFalse($response->hasHeader('Expires'));
        self::assertFalse($response->hasHeader('Last-Modified'));
        self::assertFalse($response->hasHeader('Cache-Control'));
        self::assertFalse($response->hasHeader('Pragma'));
    }

    /** @return Generator<string, array{0: string, 1: string, 2: list<string>}> */
    public static function providePreexistingCacheHeaders(): Generator
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
     * @param string[] $headersThatShouldNotBePresent
     */
    #[DataProvider('providePreexistingCacheHeaders')]
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

        $lastModified = self::getExpectedLastModified() === false ? '' : self::getExpectedLastModified();

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

        $lastModified = self::getExpectedLastModified() === false ? '' : self::getExpectedLastModified();

        self::assertMatchesRegularExpression(self::GMDATE_REGEXP, $response->getHeaderLine('Expires'));
        self::assertSame(sprintf('private, max-age=%d', $maxAge), $response->getHeaderLine('Cache-Control'));
        self::assertSame($lastModified, $response->getHeaderLine('Last-Modified'));
        self::assertFalse($response->hasHeader('Pragma'));
    }

    #[DataProvider('provideCacheLimiterValues')]
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

    /**
     * @return array<string, array{
     *     cacheExpire: int,
     *     cacheLimiter: string,
     *     expectedExpires: string,
     *     expectedLastModified: string,
     *     expectedCacheControl: string,
     *     expectedPragma: string,
     * }>
     */
    public static function provideCacheLimiterValues(): array
    {
        $cacheExpire  = 60;
        $maxAge       = (string) (60 * $cacheExpire);
        $lastModified = self::getExpectedLastModified();

        return [
            'empty'     => [
                'cacheExpire'          => $cacheExpire,
                'cacheLimiter'         => '',
                'expectedExpires'      => '',
                'expectedLastModified' => '',
                'expectedCacheControl' => '',
                'expectedPragma'       => '',
            ],
            'not-valid' => [
                'cacheExpire'          => $cacheExpire,
                'cacheLimiter'         => 'not-valid',
                'expectedExpires'      => '',
                'expectedLastModified' => '',
                'expectedCacheControl' => '',
                'expectedPragma'       => '',
            ],
            'nocache'   => [
                'cacheExpire'          => $cacheExpire,
                'cacheLimiter'         => 'nocache',
                'expectedExpires'      => Http::CACHE_PAST_DATE,
                'expectedLastModified' => '',
                'expectedCacheControl' => 'no-store, no-cache, must-revalidate',
                'expectedPragma'       => 'no-cache',
            ],
            'public'    => [
                'cacheExpire'          => $cacheExpire,
                'cacheLimiter'         => 'public',
                'expectedExpires'      => gmdate(Http::DATE_FORMAT, time() + (60 * $cacheExpire)),
                'expectedLastModified' => (string) $lastModified,
                'expectedCacheControl' => 'public, max-age=' . $maxAge,
                'expectedPragma'       => '',
            ],
            'private'   => [
                'cacheExpire'          => $cacheExpire,
                'cacheLimiter'         => 'private',
                'expectedExpires'      => Http::CACHE_PAST_DATE,
                'expectedLastModified' => (string) $lastModified,
                'expectedCacheControl' => 'private, max-age=' . $maxAge,
                'expectedPragma'       => '',
            ],
        ];
    }

    private static function getExpectedLastModified(): string|false
    {
        $lastmod = getlastmod();
        if ($lastmod === false) {
            $rc        = new ReflectionClass(CacheHeadersGeneratorTrait::class);
            $classFile = $rc->getFileName();
            $lastmod   = filemtime($classFile);
        }

        return $lastmod !== false ? gmdate(Http::DATE_FORMAT, $lastmod) : false;
    }
}
