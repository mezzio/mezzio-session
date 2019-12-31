<?php

/**
 * @see       https://github.com/mezzio/mezzio-session for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-session/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-session/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Mezzio\Session\Exception;

use InvalidArgumentException;

class InvalidHopsValueException extends InvalidArgumentException implements ExceptionInterface
{
    public static function valueTooLow(string $key, int $hops) : self
    {
        return new self(sprintf(
            'Hops value specified for flash message "%s" was too low; must be greater than 0, received %d',
            $key,
            $hops
        ));
    }
}
