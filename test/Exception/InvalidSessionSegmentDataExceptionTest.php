<?php

declare(strict_types=1);

namespace MezzioTest\Session\Exception;

use Mezzio\Session\Exception\InvalidSessionSegmentDataException;
use PHPUnit\Framework\TestCase;

class InvalidSessionSegmentDataExceptionTest extends TestCase
{
    public function testExpectedExceptionMessage(): void
    {
        $e = InvalidSessionSegmentDataException::whenRetrieving('muppets', 'kermit');

        self::assertEquals(
            'Cannot retrieve session segment "muppets"; data exists, but as a "string" instead of an array',
            $e->getMessage(),
        );
    }
}
