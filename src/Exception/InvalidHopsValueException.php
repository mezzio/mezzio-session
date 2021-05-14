<?php

declare(strict_types=1);

namespace Mezzio\Session\Exception;

use InvalidArgumentException;

use function sprintf;

class InvalidHopsValueException extends InvalidArgumentException implements ExceptionInterface
{
    public static function valueTooLow(string $key, int $hops): self
    {
        return new self(sprintf(
            'Hops value specified for flash message "%s" was too low; must be greater than 0, received %d',
            $key,
            $hops
        ));
    }
}
