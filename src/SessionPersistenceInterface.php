<?php

declare(strict_types=1);

namespace Mezzio\Session;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface SessionPersistenceInterface
{
    /**
     * Generate a session data instance based on the request.
     */
    public function initializeSessionFromRequest(ServerRequestInterface $request): SessionInterface;

    /**
     * Persist the session data instance.
     *
     * Persists the session data, returning a response instance with any
     * artifacts required to return to the client.
     *
     * @param SessionInterface&SessionIdentifierAwareInterface $session
     */
    public function persistSession(SessionInterface $session, ResponseInterface $response): ResponseInterface;
}
