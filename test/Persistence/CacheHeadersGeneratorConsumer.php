<?php

declare(strict_types=1);

namespace MezzioTest\Session\Persistence;

use Mezzio\Session\Persistence\CacheHeadersGeneratorTrait;
use Psr\Http\Message\ResponseInterface;

final class CacheHeadersGeneratorConsumer
{
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
}
