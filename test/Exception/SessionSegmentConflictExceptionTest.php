<?php

declare(strict_types=1);

namespace MezzioTest\Session\Exception;

use Mezzio\Session\Exception\SessionSegmentConflictException;
use PHPUnit\Framework\TestCase;

class SessionSegmentConflictExceptionTest extends TestCase
{
    public function testWhenRetrieving(): void
    {
        $e = SessionSegmentConflictException::whenRetrieving('baz');
        self::assertStringContainsString('"baz"', $e->getMessage());
    }

    public function testWhenSetting(): void
    {
        $e = SessionSegmentConflictException::whenSetting('qoo');
        self::assertStringContainsString('"qoo"', $e->getMessage());
    }

    public function testWhenDeleting(): void
    {
        $e = SessionSegmentConflictException::whenDeleting('boo');
        self::assertStringContainsString('"boo"', $e->getMessage());
    }
}
