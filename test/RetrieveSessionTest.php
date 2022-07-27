<?php

declare(strict_types=1);

namespace MezzioTest\Session;

use Laminas\Diactoros\ServerRequest;
use Mezzio\Session\Exception\SessionNotInitializedException;
use Mezzio\Session\RetrieveSession;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use PHPUnit\Framework\TestCase;

/** @covers \Mezzio\Session\RetrieveSession */
final class RetrieveSessionTest extends TestCase
{
    public function testAnExceptionIsThrownRetrievingTheSessionWhenItCannotBeFoundInAnyRequestAttribute(): void
    {
        $this->expectException(SessionNotInitializedException::class);
        RetrieveSession::fromRequest(new ServerRequest());
    }

    public function testThatTheSessionWillBeNullIfItCannotBeFoundInTheRequestAttributes(): void
    {
        self::assertNull(RetrieveSession::fromRequestOrNull(new ServerRequest()));
    }

    /** @return array<string, array{0: string}> */
    public function attributeNameProvider(): array
    {
        return [
            SessionMiddleware::SESSION_ATTRIBUTE => [SessionMiddleware::SESSION_ATTRIBUTE],
            SessionInterface::class              => [SessionInterface::class],
        ];
    }

    /** @dataProvider attributeNameProvider */
    public function testSuccessfulSessionRetrieval(string $attributeName): void
    {
        $session = $this->createMock(SessionInterface::class);
        $request = (new ServerRequest())->withAttribute($attributeName, $session);

        self::assertSame($session, RetrieveSession::fromRequest($request));
        self::assertSame($session, RetrieveSession::fromRequestOrNull($request));
    }
}
