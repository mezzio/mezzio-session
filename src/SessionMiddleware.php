<?php

declare(strict_types=1);

namespace Mezzio\Session;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** @final */
class SessionMiddleware implements MiddlewareInterface
{
    public const SESSION_ATTRIBUTE = 'session';

    public function __construct(private readonly SessionPersistenceInterface $persistence)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $session  = new LazySession($this->persistence, $request);
        $response = $handler->handle(
            $request
                ->withAttribute(self::SESSION_ATTRIBUTE, $session)
                ->withAttribute(SessionInterface::class, $session)
        );
        return $this->persistence->persistSession($session, $response);
    }
}
