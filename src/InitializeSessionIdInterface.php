<?php

declare(strict_types=1);

namespace Mezzio\Session;

interface InitializeSessionIdInterface
{
    /**
     * Returns id of session, generating / regenerating if required
     */
    public function initializeId(): string;
}
