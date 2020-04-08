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
                'Expires'       => Http::CACHE_PAST_DATE,
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma'        => 'no-cache',
            ];
        }

        $maxAge       = 60 * $this->cacheExpire;
        $lastModified = $this->getLastModified();

        // cache_limiter: 'public'
        if ('public' === $this->cacheLimiter) {
            return [
                'Expires'       => gmdate(Http::DATE_FORMAT, time() + $maxAge),
                'Cache-Control' => sprintf('public, max-age=%d', $maxAge),
                'Last-Modified' => $lastModified,
            ];
        }

        // cache_limiter: 'private'
        if ('private' === $this->cacheLimiter) {
            return [
                'Expires'       => Http::CACHE_PAST_DATE,
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
        $this->lastModified = $lastmod ? gmdate(Http::DATE_FORMAT, $lastmod) : false;

        return $this->lastModified;
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
}
