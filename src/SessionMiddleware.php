<?php

/**
 * @see       https://github.com/mezzio/mezzio-session for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-session/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-session/blob/master/LICENSE.md New BSD License
 */

namespace Mezzio\Session;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SessionMiddleware implements MiddlewareInterface
{
    const SESSION_ATTRIBUTE = 'session';

    /**
     * @var SessionPersistenceInterface
     */
    private $persistence;

    public function __construct(SessionPersistenceInterface $persistence)
    {
        $this->persistence = $persistence;
    }

    public function process(ServerRequestInterface $request, DelegateInterface $delegate) : ResponseInterface
    {
        $session = new LazySession($this->persistence, $request);
        $response = $delegate->process($request->withAttribute(self::SESSION_ATTRIBUTE, $session));
        return $this->persistence->persistSession($session, $response);
    }
}
