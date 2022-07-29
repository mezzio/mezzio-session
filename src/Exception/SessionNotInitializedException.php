<?php

declare(strict_types=1);

namespace Mezzio\Session\Exception;

use Mezzio\Session\SessionMiddleware;
use RuntimeException;

use function sprintf;

final class SessionNotInitializedException extends RuntimeException implements ExceptionInterface
{
    public static function forMissingRequestAttribute(): self
    {
        return new self(
            sprintf(
                'A session has not been initialized. Ensure that you pipe the %s middleware prior to'
                . ' attempting to retrieve or use a session instance.',
                SessionMiddleware::class
            )
        );
    }
}
