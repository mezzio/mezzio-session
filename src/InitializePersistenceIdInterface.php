<?php

/**
 * @see       https://github.com/mezzio/mezzio-session for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-session/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-session/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Mezzio\Session;

interface InitializePersistenceIdInterface
{
    /**
     * Returns new instance with id generated / regenerated, if required
     *
     * @param SessionInterface $session
     * @return SessionInterface
     */
    public function initializeId(SessionInterface $session) : SessionInterface;
}
