<?php

declare(strict_types=1);

namespace MezzioTest\Session;

use Mezzio\Session\LazySession;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Mezzio\Session\SessionPersistenceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionProperty;

class SessionMiddlewareTest extends TestCase
{
    /**
     * @return mixed
     */
    private function getAttribute(object $instance, string $property)
    {
        $r = new ReflectionProperty($instance, $property);
        $r->setAccessible(true);
        return $r->getValue($instance);
    }

    /**
     * @param mixed $expected
     */
    private function assertAttributeSame($expected, string $property, object $instance): void
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
        /** @psalm-var ServerRequestInterface&MockObject $request */
        $request = $this->createMock(ServerRequestInterface::class);
        $request
            ->expects($this->exactly(2))
            ->method('withAttribute')
            ->withConsecutive(
                [SessionMiddleware::SESSION_ATTRIBUTE, $this->isInstanceOf(LazySession::class)],
                [SessionInterface::class, $this->isInstanceOf(LazySession::class)],
            )
            ->willReturnSelf();

        /** @psalm-var ResponseInterface&MockObject $response */
        $response = $this->createMock(ResponseInterface::class);

        /** @psalm-var RequestHandlerInterface&MockObject $handler */
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler
            ->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        /** @psalm-var SessionPersistenceInterface&MockObject $persistence */
        $persistence = $this->createMock(SessionPersistenceInterface::class);
        $persistence
            ->expects($this->once())
            ->method('persistSession')
            ->with(
                $this->callback(function (SessionInterface $session) use ($persistence, $request) {
                    return $session instanceof LazySession
                        && $persistence === $this->getAttribute($session, 'persistence')
                        && $request === $this->getAttribute($session, 'request');
                }),
                $response
            )
            ->willReturn($response);

        $middleware = new SessionMiddleware($persistence);
        $this->assertSame($response, $middleware->process($request, $handler));
    }
}
