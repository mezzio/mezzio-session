<?php

declare(strict_types=1);

namespace MezzioTest\Session\Exception;

use Mezzio\Session\Exception\InvalidHopsValueException;
use PHPUnit\Framework\TestCase;

class InvalidHopsValueExceptionTest extends TestCase
{
    public function testExpectedMessageWhenHopsTooLow(): void
    {
        $e = InvalidHopsValueException::valueTooLow('nuts', 99);

        self::assertStringContainsString('"nuts"', $e->getMessage());
        self::assertStringContainsString('received 99', $e->getMessage());
    }
}
