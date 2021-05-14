<?php

declare(strict_types=1);

namespace Mezzio\Session\Exception;

use Mezzio\Session\InitializePersistenceIdInterface;
use Mezzio\Session\SessionPersistenceInterface;
use RuntimeException;

use function get_class;
use function sprintf;

final class NotInitializableException extends RuntimeException implements ExceptionInterface
{
    public static function invalidPersistence(SessionPersistenceInterface $persistence): self
    {
        return new self(sprintf(
            "Persistence '%s' does not implement '%s'",
            get_class($persistence),
            InitializePersistenceIdInterface::class
        ));
    }
}
