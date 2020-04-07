<?php

/**
 * @see       https://github.com/mezzio/mezzio-session for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-session/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-session/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Mezzio\Session\Persistence;

use Psr\Http\Message\ResponseInterface;

use function filemtime;
use function getlastmod;
use function gmdate;
use function sprintf;
use function time;

/**
 * Provides cache-headers generation methods to consumer classes.
 */
trait CacheHeadersGeneratorTrait
{
    /**
     * The time-to-live for cached session pages in minutes as specified in php
     * ini settings. This has no effect for 'nocache' limiter.
     *
     * @var int
     */
    private $cacheExpire;

    /**
     * The cache control method used for session pages as specified in php ini
     * settings. It may be one of the following values: 'nocache', 'private',
     * 'private_no_expire', or 'public'.
     *
     * @var string
     */
    private $cacheLimiter;

    /** @var array */
    private static $supportedCacheLimiters = [
        'nocache'           => true,
        'public'            => true,
        'private'           => true,
        'private_no_expire' => true,
    ];

    /** @var false|string */
    private $lastModified;

    /**
     * HTTP date format to be used in `gmdate` calls for creating valid header
     * values.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Date
     * @see https://tools.ietf.org/html/rfc7231#section-7.1.1.2
     */
    private static $httpDateFormat = 'D, d M Y H:i:s T';

    /**
     * This unusual past date value is taken from the php-engine source code and
     * used "as is" for consistency. BTW, it's Sebastian Bergmann's birthdate.
     *
     * @see https://github.com/php/php-src/blob/php-7.4.4/ext/session/session.c#L1193
     */
    private static $cachePastDate = 'Thu, 19 Nov 1981 08:52:00 GMT';

    /**
     * Add cache headers to the response when needed.
     */
    private function addCacheHeadersToResponse(ResponseInterface $response) : ResponseInterface
    {
        if (! $this->cacheLimiter || $this->responseAlreadyHasCacheHeaders($response)) {
            return $response;
        }

        $cacheHeaders = $this->generateCacheHeaders();
        foreach ($cacheHeaders as $name => $value) {
            if ($value !== false) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }

    /**
     * Generate cache http headers for this instance's session cache-limiter and
     * cache-expire values.
     */
    private function generateCacheHeaders() : array
    {
        // Unsupported cache_limiter => do not generate cache headers
        if (! isset(self::$supportedCacheLimiters[$this->cacheLimiter])) {
            return [];
        }

        // cache_limiter: 'nocache'
        if ('nocache' === $this->cacheLimiter) {
            return [
                'Expires'       => self::$cachePastDate,
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma'        => 'no-cache',
            ];
        }

        $maxAge       = 60 * $this->cacheExpire;
        $lastModified = $this->getLastModified();

        // cache_limiter: 'public'
        if ('public' === $this->cacheLimiter) {
            return [
                'Expires'       => gmdate(self::$httpDateFormat, time() + $maxAge),
                'Cache-Control' => sprintf('public, max-age=%d', $maxAge),
                'Last-Modified' => $lastModified,
            ];
        }

        // cache_limiter: 'private'
        if ('private' === $this->cacheLimiter) {
            return [
                'Expires'       => self::$cachePastDate,
                'Cache-Control' => sprintf('private, max-age=%d', $maxAge),
                'Last-Modified' => $lastModified,
            ];
        }

        // last possible case, cache_limiter = 'private_no_expire'
        return [
            'Cache-Control' => sprintf('private, max-age=%d', $maxAge),
            'Last-Modified' => $lastModified,
        ];
    }

    /**
     * Return the Last-Modified header line based on main script of execution
     * modified time. If unable to get a valid timestamp we use this class file
     * modification time as fallback.
     *
     * @return string|false
     */
    private function getLastModified()
    {
        if (isset($this->lastModified)) {
            return $this->lastModified;
        }

        $lastmod = getlastmod() ?: filemtime(__FILE__);
        $lastmod ? gmdate(self::$httpDateFormat, $lastmod) : false;
        $this->lastModified = $lastmod;

        return $lastmod;
    }

    /**
     * Check if the response already carries cache headers
     */
    private function responseAlreadyHasCacheHeaders(ResponseInterface $response) : bool
    {
        return $response->hasHeader('Expires')
            || $response->hasHeader('Last-Modified')
            || $response->hasHeader('Cache-Control')
            || $response->hasHeader('Pragma');
    }

    /**
     * @internal
     */
    public static function getHttpDateFormat() : string
    {
        return self::$httpDateFormat;
    }

    /**
     * @internal
     */
    public static function getCachePastDate() : string
    {
        return self::$cachePastDate;
    }
}
