<?php

declare(strict_types=1);

namespace Mezzio\Session;

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
            // phpcs:disable WebimpressCodingStandard.Formatting.StringClassReference.Found
            'aliases'   => [
                'Zend\Expressive\Session\SessionMiddleware' => SessionMiddleware::class,
            ],
            'factories' => [
                SessionMiddleware::class => SessionMiddlewareFactory::class,
            ],
        ];
    }
}
