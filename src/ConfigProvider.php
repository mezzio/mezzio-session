<?php

declare(strict_types=1);

namespace Mezzio\Session;

use Zend\Expressive\Session\SessionMiddleware as LegacySessionMiddleware;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    public function getDependencies(): array
    {
        return [
            // Legacy Zend Framework aliases
            'aliases'   => [
                LegacySessionMiddleware::class => SessionMiddleware::class,
            ],
            'factories' => [
                SessionMiddleware::class => SessionMiddlewareFactory::class,
            ],
        ];
    }
}
