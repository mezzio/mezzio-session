<?php

/**
 * @see       https://github.com/mezzio/mezzio-session for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-session/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-session/blob/master/LICENSE.md New BSD License
 */


declare(strict_types=1);

namespace Mezzio\Session;

interface InitializeSessionIdInterface
{
    /**
     * Returns id of session, generating / regenerating if required
     *
     * @return string
     */
    public function initializeId() : string;
}
