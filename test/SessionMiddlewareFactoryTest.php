<?php

/**
 * @see       https://github.com/mezzio/mezzio-session for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-session/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-session/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace MezzioTest\Session;

use Mezzio\Session\SessionMiddleware;
use Mezzio\Session\SessionMiddlewareFactory;
use Mezzio\Session\SessionPersistenceInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class SessionMiddlewareFactoryTest extends TestCase
{
    public function testFactoryProducesMiddlewareWithSessionPersistenceInterfaceService()
    {
        $persistence = $this->prophesize(SessionPersistenceInterface::class)->reveal();

        $container = $this->prophesize(ContainerInterface::class);
        $container->get(SessionPersistenceInterface::class)->willReturn($persistence);

        $factory = new SessionMiddlewareFactory();

        $middleware = $factory($container->reveal());

        $this->assertInstanceOf(SessionMiddleware::class, $middleware);
        $this->assertAttributeSame($persistence, 'persistence', $middleware);
    }
}
