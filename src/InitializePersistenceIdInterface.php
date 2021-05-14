<?php

declare(strict_types=1);

namespace Mezzio\Session;

interface InitializePersistenceIdInterface
{
    /**
     * Returns new instance with id generated / regenerated, if required
     */
    public function initializeId(SessionInterface $session): SessionInterface;
}
