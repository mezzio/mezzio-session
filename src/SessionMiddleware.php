<?php

declare(strict_types=1);

namespace Mezzio\Session;

use Mezzio\Session\SessionPersistenceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SessionMiddleware implements MiddlewareInterface
{
    public const SESSION_ATTRIBUTE = 'session';

    private SessionPersistenceInterface $persistence;

    public function __construct(SessionPersistenceInterface $persistence)
    {
        $this->persistence = $persistence;
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
