<?php

/**
 * @see       https://github.com/mezzio/mezzio-session for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-session/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-session/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Mezzio\Session\Exception;

use Mezzio\Session\InitializePersistenceIdInterface;
use Mezzio\Session\SessionPersistenceInterface;
use RuntimeException;

final class NotInitializableException extends RuntimeException implements ExceptionInterface
{
    public static function invalidPersistence(SessionPersistenceInterface $persistence) : self
    {
        return new self(sprintf(
            "Persistence '%s' does not implement '%s'",
            get_class($persistence),
            InitializePersistenceIdInterface::class
        ));
    }
}
