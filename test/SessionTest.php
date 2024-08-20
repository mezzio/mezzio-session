<?php

declare(strict_types=1);

namespace MezzioTest\Session;

use Mezzio\Session\Session;
use Mezzio\Session\SessionCookiePersistenceInterface;
use Mezzio\Session\SessionInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

use function json_decode;
use function json_encode;

use const JSON_PRESERVE_ZERO_FRACTION;

class SessionTest extends TestCase
{
    public function testImplementsSessionInterface(): void
    {
        $session = new Session([], 'test');
        $this->assertInstanceOf(SessionInterface::class, $session);
    }

    public function testIsNotChangedAtInstantiation(): void
    {
        $session = new Session([], 'test');
        $this->assertFalse($session->hasChanged());
    }

    public function testIsNotRegeneratedByDefault(): void
    {
        $session = new Session([], 'test');
        $this->assertFalse($session->isRegenerated());
    }

    public function testRegenerateProducesANewInstance(): SessionInterface
    {
        $session     = new Session([], 'test');
        $regenerated = $session->regenerate();
        $this->assertNotSame($session, $regenerated);
        return $regenerated;
    }

    #[Depends('testRegenerateProducesANewInstance')]
    public function testRegeneratedSessionReturnsTrueForIsRegenerated(SessionInterface $session): void
    {
        $this->assertTrue($session->isRegenerated());
    }

    #[Depends('testRegenerateProducesANewInstance')]
    public function testRegeneratedSessionIsChanged(SessionInterface $session): void
    {
        $this->assertTrue($session->hasChanged());
    }

    public function testSettingDataInSessionMakesItAccessible(): Session
    {
        $session = new Session([], 'test');
        $this->assertFalse($session->has('foo'));
        $session->set('foo', 'bar');
        $this->assertTrue($session->has('foo'));
        $this->assertSame('bar', $session->get('foo'));
        return $session;
    }

    #[Depends('testSettingDataInSessionMakesItAccessible')]
    public function testSettingDataInSessionChangesSession(SessionInterface $session): void
    {
        $this->assertTrue($session->hasChanged());
    }

    #[Depends('testSettingDataInSessionMakesItAccessible')]
    public function testToArrayReturnsAllDataPreviouslySet(SessionInterface $session): void
    {
        $this->assertSame(['foo' => 'bar'], $session->toArray());
    }

    #[Depends('testSettingDataInSessionMakesItAccessible')]
    public function testCanUnsetDataInSession(SessionInterface $session): void
    {
        $session->unset('foo');
        $this->assertFalse($session->has('foo'));
    }

    public function testClearingSessionRemovesAllData(): void
    {
        $original = [
            'foo' => 'bar',
            'baz' => 'bat',
        ];
        $session  = new Session($original, 'test');
        $this->assertSame($original, $session->toArray());

        $session->clear();
        $this->assertNotSame($original, $session->toArray());
        $this->assertSame([], $session->toArray());
    }

    /**
     * @psalm-return array<string, array{object, array<array-key, mixed>}>
     */
    public static function serializedDataProvider(): array
    {
        $data = (object) [
            'test_case' => new class () {
                private string $foo = 'bar';
            },
        ];
        /** @var array $expected */
        $expected = json_decode(json_encode($data, JSON_PRESERVE_ZERO_FRACTION), true);
        return [
            'nested-objects' => [$data, $expected],
        ];
    }

    #[DataProvider('serializedDataProvider')]
    public function testSetEnsuresDataIsJsonSerializable(object $data, array $expected): void
    {
        $session = new Session([], 'test');
        $session->set('foo', $data);
        $this->assertNotSame($data, $session->get('foo'));
        $this->assertSame($expected, $session->get('foo'));
    }

    public function testGetIdReturnsValueProvidedToConstructor(): void
    {
        $session = new Session([], '1234abcd');
        $this->assertSame('1234abcd', $session->getId());
    }

    public function testImplementsSessionCookiePersistenceInterface(): void
    {
        $session = new Session([], 'test');
        $this->assertInstanceOf(SessionCookiePersistenceInterface::class, $session);
    }

    public function testDefaultSessionCookieLifetimeIsZero(): void
    {
        $session = new Session([], 'test');
        $this->assertSame(0, $session->getSessionLifetime());
    }

    public function testAllowsSettingCookieLifetime(): void
    {
        $session = new Session([], 'test');
        $session->persistSessionFor(60);
        $this->assertSame(60, $session->getSessionLifetime());
    }

    public function testGetSessionLifetimeReturnsValueOfSessionLifetimeKeyWhenPresentInSession(): void
    {
        $session = new Session([
            SessionCookiePersistenceInterface::SESSION_LIFETIME_KEY => 60,
        ], 'test');
        $this->assertSame(60, $session->getSessionLifetime());
    }

    public function testPersistingSessionCookieLifetimeSetsLifetimeKeyInSessionData(): void
    {
        $session = new Session([], 'test');
        $session->persistSessionFor(60);
        $this->assertTrue($session->has(SessionCookiePersistenceInterface::SESSION_LIFETIME_KEY));
        $this->assertSame(60, $session->get(SessionCookiePersistenceInterface::SESSION_LIFETIME_KEY));
    }
}
