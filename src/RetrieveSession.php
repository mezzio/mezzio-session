<?php

declare(strict_types=1);

namespace Mezzio\Session;

use Mezzio\Session\Exception\SessionNotInitializedException;
use Psr\Http\Message\ServerRequestInterface;

final class RetrieveSession
{
    private function __construct()
    {
    }

    /**
     * A convenience method to retrieve the current session from a request.
     *
     * @throws SessionNotInitializedException If a session is not present in the attributes of the given request.
     */
    public static function fromRequest(ServerRequestInterface $request): SessionInterface
    {
        $session = self::fromRequestOrNull($request);
        if (! $session instanceof SessionInterface) {
            throw SessionNotInitializedException::forMissingRequestAttribute();
        }

        return $session;
    }

    /**
     * A convenience method to retrieve the current session from a request or null if uninitialized.
     */
    public static function fromRequestOrNull(ServerRequestInterface $request): ?SessionInterface
    {
        $attributes = [
            SessionMiddleware::SESSION_ATTRIBUTE,
            SessionInterface::class,
        ];

        foreach ($attributes as $attribute) {
            /** @psalm-var mixed $value */
            $value = $request->getAttribute($attribute);
            if (! $value instanceof SessionInterface) {
                continue;
            }

            return $value;
        }

        return null;
    }
}
