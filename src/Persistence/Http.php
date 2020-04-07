<?php

/**
 * @see       https://github.com/mezzio/mezzio-session for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-session/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-session/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Mezzio\Session\Persistence;

/**
 * Provides commonly used HTTP related constants.
 */
class Http
{
    /**
     * HTTP date format to be used in `gmdate` calls for creating valid header
     * values.
     *
     * òsee https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Date
     * @see https://tools.ietf.org/html/rfc7231#section-7.1.1.2
     */
    public const DATE_FORMAT = 'D, d M Y H:i:s T';

    /**
     * The header formatted version of the Unix Epoch for generic past-date.
     */
    public const UNIX_EPOCH = 'Thu, 01 Jan 1970 00:00:00 GMT';

    /**
     * This unusual past date value is taken from the php-engine source code and
     * used "as is" for consistency. BTW, it's Sebastian Bergmann's birthdate.
     *
     * @see https://github.com/php/php-src/blob/php-7.4.4/ext/session/session.c#L1193
     */
    public const CACHE_PAST_DATE = 'Thu, 19 Nov 1981 08:52:00 GMT';
}
