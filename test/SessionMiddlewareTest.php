<?php

declare(strict_types=1);

namespace MezzioTest\Session;

use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Session\LazySession;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Mezzio\Session\SessionPersistenceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class SessionMiddlewareTest extends TestCase
{
    private function getAttribute(object $instance, string $property): mixed
    {
        $r = new ReflectionProperty($instance, $property);

        return $r->getValue($instance);
    }

    private function assertAttributeSame(mixed $expected, string $property, object $instance): void
    {
        $this->assertSame($expected, $this->getAttribute($instance, $property));
    }

    public function testConstructorAcceptsConcretePersistenceInstances(): void
    {
        $persistence = $this->createMock(SessionPersistenceInterface::class);
        $middleware  = new SessionMiddleware($persistence);

        $this->assertAttributeSame($persistence, 'persistence', $middleware);
    }

    public function testMiddlewareCreatesLazySessionAndPassesItToDelegateAndPersistsSessionInResponse(): void
    {
        $request  = new ServerRequest();
        $response = new TextResponse('Foo');
        $handler  = new RequestHandler($response);

        /** @psalm-var SessionPersistenceInterface&MockObject $persistence */
        $persistence = $this->createMock(SessionPersistenceInterface::class);
        $persistence
            ->expects($this->once())
            ->method('persistSession')
            ->with(
                $this->callback(fn(SessionInterface $session) => $session instanceof LazySession
                    && $persistence === $this->getAttribute($session, 'persistence')
                    && $request === $this->getAttribute($session, 'request')),
                $response
            )
            ->willReturn($response);

        $middleware = new SessionMiddleware($persistence);
        self::assertSame($response, $middleware->process($request, $handler));

        self::assertTrue($handler->didExecute());

        $received = $handler->received();

        self::assertNotSame($request, $received);
        self::assertInstanceOf(LazySession::class, $received->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE));
        self::assertInstanceOf(LazySession::class, $received->getAttribute(SessionInterface::class));
        self::assertSame(
            $received->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE),
            $received->getAttribute(SessionInterface::class),
        );
    }
}
