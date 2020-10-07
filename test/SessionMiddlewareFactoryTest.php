<?php

/**
 * @see       https://github.com/mezzio/mezzio-session for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-session/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-session/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace MezzioTest\Session;

use Mezzio\Session\SessionMiddlewareFactory;
use Mezzio\Session\SessionPersistenceInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionProperty;

class SessionMiddlewareFactoryTest extends TestCase
{
    public function testFactoryProducesMiddlewareWithSessionPersistenceInterfaceService(): void
    {
        $persistence = $this->createMock(SessionPersistenceInterface::class);

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->expects($this->once())
            ->method('get')
            ->with(SessionPersistenceInterface::class)
            ->willReturn($persistence);

        $factory = new SessionMiddlewareFactory();

        $middleware = $factory($container);

        $r = new ReflectionProperty($middleware, 'persistence');
        $r->setAccessible(true);
        $this->assertSame($persistence, $r->getValue($middleware));
    }
}
