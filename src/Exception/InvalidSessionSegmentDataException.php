<?php

declare(strict_types=1);

namespace Mezzio\Session\Exception;

use RuntimeException;

use function get_class;
use function gettype;
use function is_object;
use function sprintf;

class InvalidSessionSegmentDataException extends RuntimeException implements ExceptionInterface
{
    /**
     * @param mixed $data
     */
    public static function whenRetrieving(string $name, $data): self
    {
        return new self(sprintf(
            'Cannot retrieve session segment "%s"; data exists, but as a "%s" instead of an array',
            $name,
            is_object($data) ? get_class($data) : gettype($data)
        ));
    }
}
