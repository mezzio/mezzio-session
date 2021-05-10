<?php

declare(strict_types=1);

namespace Mezzio\Session\Exception;

use RuntimeException;

use function sprintf;

class SessionSegmentConflictException extends RuntimeException implements ExceptionInterface
{
    public static function whenRetrieving(string $name): self
    {
        return new self(sprintf(
            'Retrieving session data "%s" via get(); however, this data refers to a session segment; aborting',
            $name
        ));
    }

    public static function whenSetting(string $name): self
    {
        return new self(sprintf(
            'Attempting to set session data "%s"; however, this data refers to a session segment; aborting',
            $name
        ));
    }

    public static function whenDeleting(string $name): self
    {
        return new self(sprintf(
            'Attempting to unset session data "%s"; however, this data refers to a session segment. '
            . 'Use clear() on the segment instead',
            $name
        ));
    }
}
