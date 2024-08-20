<?php

declare(strict_types=1);

namespace Mezzio\Session;

use function array_key_exists;
use function is_numeric;
use function json_decode;
use function json_encode;

use const JSON_PRESERVE_ZERO_FRACTION;

class Session implements
    SessionCookiePersistenceInterface,
    SessionInterface
{
    /**
     * Current data within the session.
     *
     * @var array<string, mixed>
     */
    private array $data;

    /**
     * The session identifier, if any.
     *
     * This is present in the session to allow the session persistence
     * implementation to be stateless. When present here, we can query for it
     * when it is time to persist the session, instead of relying on state in
     * the persistence instance (which may be shared between multiple
     * requests).
     */
    private string $id;

    private bool $isRegenerated = false;

    /**
     * Original data provided to the constructor.
     *
     * @var array
     */
    private $originalData;

    /**
     * Lifetime of the session cookie.
     */
    private int $sessionLifetime = 0;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, string $id = '')
    {
        $this->data = $this->originalData = $data;
        $this->id   = $id;

        /** @psalm-suppress MixedAssignment */
        $lifetime = $data[SessionCookiePersistenceInterface::SESSION_LIFETIME_KEY] ?? null;

        if (is_numeric($lifetime)) {
            $this->sessionLifetime = (int) $lifetime;
        }
    }

    /**
     * Convert a value to a JSON-serializable value.
     *
     * This value should be used by `set()` operations to ensure that the values
     * within a session are serializable across any session adapter.
     *
     * @param mixed $value
     * @return null|bool|int|float|string|array
     */
    public static function extractSerializableValue($value)
    {
        return json_decode(json_encode($value, JSON_PRESERVE_ZERO_FRACTION), true);
    }

    /**
     * Retrieve all data for purposes of persistence.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @param null|mixed $default Default value to return if $name does not exist.
     * @return mixed
     */
    public function get(string $name, $default = null)
    {
        return $this->data[$name] ?? $default;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }

    /**
     * @param mixed $value
     */
    public function set(string $name, $value): void
    {
        $this->data[$name] = self::extractSerializableValue($value);
    }

    public function unset(string $name): void
    {
        unset($this->data[$name]);
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function hasChanged(): bool
    {
        if ($this->isRegenerated) {
            return true;
        }

        return $this->data !== $this->originalData;
    }

    public function regenerate(): SessionInterface
    {
        $session                = clone $this;
        $session->isRegenerated = true;
        return $session;
    }

    public function isRegenerated(): bool
    {
        return $this->isRegenerated;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.1.0
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.2.0
     */
    public function persistSessionFor(int $duration): void
    {
        $this->sessionLifetime = $duration;
        $this->set(SessionCookiePersistenceInterface::SESSION_LIFETIME_KEY, $duration);
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.2.0
     */
    public function getSessionLifetime(): int
    {
        return $this->sessionLifetime;
    }
}
