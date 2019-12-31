<?php

/**
 * @see       https://github.com/mezzio/mezzio-session for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-session/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-session/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Mezzio\Session;

use Psr\Container\ContainerInterface;

class SessionMiddlewareFactory
{
    public function __invoke(ContainerInterface $container) : SessionMiddleware
    {
        return new SessionMiddleware(
            $container->get(SessionPersistenceInterface::class)
        );
    }
}
