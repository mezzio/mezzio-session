<?php

/**
 * @see       https://github.com/mezzio/mezzio-session for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-session/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-session/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Mezzio\Session\Exception;

use RuntimeException;

class SessionSegmentConflictException extends RuntimeException implements ExceptionInterface
{
    public static function whenRetrieving(string $name) : self
    {
        return new self(sprintf(
            'Retrieving session data "%s" via get(); however, this data refers to a session segment; aborting',
            $name
        ));
    }

    public static function whenSetting(string $name) : self
    {
        return new self(sprintf(
            'Attempting to set session data "%s"; however, this data refers to a session segment; aborting',
            $name
        ));
    }

    public static function whenDeleting(string $name) : self
    {
        return new self(sprintf(
            'Attempting to unset session data "%s"; however, this data refers to a session segment. '
            . 'Use clear() on the segment instead',
            $name
        ));
    }
}
