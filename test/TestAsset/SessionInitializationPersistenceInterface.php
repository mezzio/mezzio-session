<?php

declare(strict_types=1);

namespace MezzioTest\Session\TestAsset;

use Mezzio\Session\InitializePersistenceIdInterface;
use Mezzio\Session\SessionPersistenceInterface;

interface SessionInitializationPersistenceInterface extends
    SessionPersistenceInterface,
    InitializePersistenceIdInterface
{
}
