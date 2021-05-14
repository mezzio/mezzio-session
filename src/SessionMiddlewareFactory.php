<?php

declare(strict_types=1);

namespace Mezzio\Session;

use Psr\Container\ContainerInterface;

class SessionMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): SessionMiddleware
    {
        return new SessionMiddleware(
            $container->get(SessionPersistenceInterface::class)
        );
    }
}
