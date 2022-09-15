<?php

declare(strict_types=1);

namespace Mezzio\Session;

use Mezzio\Session\Exception\NotInitializableException;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionPersistenceInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Proxy to an underlying SessionInterface implementation.
 *
 * In order to delay parsing of session data until it is accessed, use this
 * class. It will call the composed SessionPersistenceInterface's createSession()
 * method only on access to any of the various session data methods; otherwise,
 * the session will not be accessed, and, in most cases, started.
 */
final class LazySession implements
    SessionCookiePersistenceInterface,
    SessionIdentifierAwareInterface,
    SessionInterface,
    InitializeSessionIdInterface
{
    private SessionPersistenceInterface $persistence;

    private ?SessionInterface $proxiedSession = null;

    /**
     * Request instance to use when calling $persistence->initializeSessionFromRequest()
     */
    private ServerRequestInterface $request;

    public function __construct(SessionPersistenceInterface $persistence, ServerRequestInterface $request)
    {
        $this->persistence = $persistence;
        $this->request     = $request;
    }

    public function regenerate(): SessionInterface
    {
        $this->proxiedSession = $this->getProxiedSession()->regenerate();
        return $this;
    }

    public function isRegenerated(): bool
    {
        if (! $this->proxiedSession instanceof SessionInterface) {
            return false;
        }

        return $this->proxiedSession->isRegenerated();
    }

    public function toArray(): array
    {
        return $this->getProxiedSession()->toArray();
    }

    /**
     * @param null|mixed $default
     * @return mixed
     */
    public function get(string $name, $default = null)
    {
        return $this->getProxiedSession()->get($name, $default);
    }

    public function has(string $name): bool
    {
        return $this->getProxiedSession()->has($name);
    }

    /**
     * @param mixed $value
     */
    public function set(string $name, $value): void
    {
        $this->getProxiedSession()->set($name, $value);
    }

    public function unset(string $name): void
    {
        $this->getProxiedSession()->unset($name);
    }

    public function clear(): void
    {
        $this->getProxiedSession()->clear();
    }

    public function hasChanged(): bool
    {
        if (! $this->proxiedSession instanceof SessionInterface) {
            return false;
        }

        if ($this->proxiedSession->isRegenerated()) {
            return true;
        }

        return $this->proxiedSession->hasChanged();
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.1.0
     */
    public function getId(): string
    {
        $proxiedSession = $this->getProxiedSession();
        return $proxiedSession instanceof SessionIdentifierAwareInterface
            ? $proxiedSession->getId()
            : '';
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.2.0
     */
    public function persistSessionFor(int $duration): void
    {
        $proxiedSession = $this->getProxiedSession();
        if ($proxiedSession instanceof SessionCookiePersistenceInterface) {
            $proxiedSession->persistSessionFor($duration);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.2.0
     */
    public function getSessionLifetime(): int
    {
        $proxiedSession = $this->getProxiedSession();
        return $proxiedSession instanceof SessionCookiePersistenceInterface
            ? $proxiedSession->getSessionLifetime()
            : 0;
    }

    public function initializeId(): string
    {
        if (! $this->persistence instanceof InitializePersistenceIdInterface) {
            throw NotInitializableException::invalidPersistence($this->persistence);
        }

        $this->proxiedSession = $this->persistence->initializeId($this->getProxiedSession());

        return $this->getId();
    }

    private function getProxiedSession(): SessionInterface
    {
        if ($this->proxiedSession) {
            return $this->proxiedSession;
        }

        $this->proxiedSession = $this->persistence->initializeSessionFromRequest($this->request);
        return $this->proxiedSession;
    }
}
