<?php

namespace Mezzio\Session;

interface SessionIdentifierAwareInterface
{
    /**
     * Retrieve the session identifier.
     *
     * This feature was added in 1.1.0 to allow the session persistence to be
     * stateless. Previously, persistence implementations had to store the
     * session identifier between calls to initializeSessionFromRequest() and
     * persistSession(). When SessionInterface implementations also implement
     * this method, the persistence implementation no longer needs to store it.
     *
     * This method will become a part of the SessionInterface in 2.0.0.
     *
     * @since 1.1.0
     */
    public function getId(): string;
}
