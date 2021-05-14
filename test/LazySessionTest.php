<?php

declare(strict_types=1);

namespace MezzioTest\Session\LazySessionTest;

use Mezzio\Session\Exception\NotInitializableException;
use Mezzio\Session\LazySession;
use Mezzio\Session\Session;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionPersistenceInterface;
use MezzioTest\Session\TestAsset\SessionInitializationPersistenceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionProperty;

class LazySessionTest extends TestCase
{
    /**
     * @var SessionInterface|MockObject
     * @psalm-var SessionInterface&MockObject
     */
    private $proxy;

    /**
     * @var SessionPersistenceInterface|MockObject
     * @psalm-var SessionPersistenceInterface&MockObject
     */
    private $persistence;

    /**
     * @var ServerRequestInterface|MockObject
     * @psalm-var ServerRequestInterface&MockObject
     */
    private $request;

    /** @var LazySession */
    private $session;

    public function setUp(): void
    {
        $this->proxy       = $this->createMock(SessionInterface::class);
        $this->persistence = $this->createMock(SessionPersistenceInterface::class);
        $this->request     = $this->createMock(ServerRequestInterface::class);
        $this->session     = new LazySession($this->persistence, $this->request);
    }

    /**
     * @param SessionPersistenceInterface|MockObject $persistence
     * @param ServerRequestInterface|MockObject      $request
     * @psalm-param SessionPersistenceInterface&MockObject $persistence
     * @psalm-param ServerRequestInterface&MockObject      $request
     */
    public function assertProxyCreated($persistence, $request): void
    {
        $persistence
            ->method('initializeSessionFromRequest')
            ->with($request)
            ->willReturn($this->proxy);
    }

    public function initializeProxy(): void
    {
        $this->proxy->expects($this->once())->method('has')->with('foo')->willReturn(true);
        $this->session->has('foo');
    }

    public function testRegenerateWillReturnSameInstance(): void
    {
        $newSession = $this->createMock(SessionInterface::class);

        $this->assertProxyCreated($this->persistence, $this->request);
        $this->proxy->method('regenerate')->willReturn($newSession);

        $regeneratedSession = $this->session->regenerate();
        $this->assertSame($this->session, $regeneratedSession);

        $r = new ReflectionProperty($regeneratedSession, 'proxiedSession');
        $r->setAccessible(true);
        $this->assertSame($newSession, $r->getValue($regeneratedSession));
    }

    public function testIsRegeneratedReturnsTrueAfterSessionRegeneration(): void
    {
        $newSession = $this->createMock(SessionInterface::class);
        $newSession->expects($this->once())->method('isRegenerated')->willReturn(true);

        $this->assertProxyCreated($this->persistence, $this->request);
        $this->proxy->method('regenerate')->willReturn($newSession);

        $this->session->regenerate();

        $this->assertTrue($this->session->isRegenerated());
    }

    public function testIsRegneratedReturnsFalseIfProxiedSessionIsNotRegenerated(): void
    {
        $this->assertProxyCreated($this->persistence, $this->request);
        $this->proxy->method('isRegenerated')->willReturn(false);
        $this->assertFalse($this->session->isRegenerated());
    }

    public function testToArrayProxiesToUnderlyingSession(): void
    {
        $expected = ['foo' => 'bar'];
        $this->assertProxyCreated($this->persistence, $this->request);
        $this->proxy->method('toArray')->willReturn($expected);
        $this->assertSame($expected, $this->session->toArray());
    }

    public function testGetProxiesToUnderlyingSession(): void
    {
        $expected = 'foo';
        $this->assertProxyCreated($this->persistence, $this->request);
        $this->proxy->method('get')->with('test', 'bar')->willReturn($expected);
        $this->assertSame($expected, $this->session->get('test', 'bar'));
    }

    public function testHasProxiesToUnderlyingSession(): void
    {
        $this->assertProxyCreated($this->persistence, $this->request);
        $this->proxy->method('has')->with('test')->willReturn(true);
        $this->assertTrue($this->session->has('test'));
    }

    public function testSetProxiesToUnderlyingSession(): void
    {
        $this->assertProxyCreated($this->persistence, $this->request);
        $this->proxy->expects($this->once())->method('set')->with('test', 'bar');
        $this->assertNull($this->session->set('test', 'bar'));
    }

    public function testUnsetProxiesToUnderlyingSession(): void
    {
        $this->assertProxyCreated($this->persistence, $this->request);
        $this->proxy->expects($this->once())->method('unset')->with('test');
        $this->assertNull($this->session->unset('test'));
    }

    public function testClearProxiesToUnderlyingSession(): void
    {
        $this->assertProxyCreated($this->persistence, $this->request);
        $this->proxy->expects($this->once())->method('clear');
        $this->assertNull($this->session->clear());
    }

    public function testHasChangedReturnsFalseIfProxyNotInitialized(): void
    {
        $this->proxy->expects($this->never())->method('hasChanged');
        $this->assertFalse($this->session->hasChanged());
    }

    public function testHasChangedReturnsFalseIfProxyInitializedAndDoesNotHaveChanges(): void
    {
        $this->assertProxyCreated($this->persistence, $this->request);
        $this->initializeProxy();
        $this->proxy->method('isRegenerated')->willReturn(false);
        $this->proxy->method('hasChanged')->willReturn(false);
        $this->assertFalse($this->session->hasChanged());
    }

    public function testHasChangedReturnsTrueIfProxyInitializedAndHasChanges(): void
    {
        $this->assertProxyCreated($this->persistence, $this->request);
        $this->initializeProxy();
        $this->proxy->method('isRegenerated')->willReturn(false);
        $this->proxy->method('hasChanged')->willReturn(true);
        $this->assertTrue($this->session->hasChanged());
    }

    public function testHasChangedReturnsTrueIfProxyHasBeenRegenerated(): void
    {
        $this->assertProxyCreated($this->persistence, $this->request);
        $this->initializeProxy();
        $this->proxy->method('isRegenerated')->willReturn(true);
        $this->proxy->expects($this->never())->method('hasChanged');
        $this->assertTrue($this->session->hasChanged());
    }

    public function testGetIdReturnsEmptyStringIfProxyDoesNotImplementIdentifierAwareInterface(): void
    {
        $this->assertProxyCreated($this->persistence, $this->request);
        $this->initializeProxy();
        $this->assertSame('', $this->session->getId());
    }

    public function testGetIdReturnsValueFromProxyIfItImplementsIdentiferAwareInterface(): void
    {
        $proxy = $this->createMock(Session::class);
        $proxy->method('getId')->willReturn('abcd1234');
        $this->persistence
            ->method('initializeSessionFromRequest')
            ->with($this->request)
            ->willReturn($proxy);

        $session = new LazySession($this->persistence, $this->request);

        $this->assertSame('abcd1234', $session->getId());
    }

    public function testPersistSessionForDoesNothingIfProxyDoesNotImplementSessionCookiePersistence(): void
    {
        $this->assertProxyCreated($this->persistence, $this->request);
        $this->initializeProxy();

        $session = new LazySession($this->persistence, $this->request);

        $this->assertNull($session->persistSessionFor(60));
    }

    public function testPersistSessionForProxiesToUnderlyingSession(): void
    {
        $proxy = $this->createMock(Session::class);
        $proxy->expects($this->once())->method('persistSessionFor')->with(60);
        $this->persistence
            ->method('initializeSessionFromRequest')
            ->with($this->request)
            ->willReturn($proxy);

        $session = new LazySession($this->persistence, $this->request);

        $this->assertNull($session->persistSessionFor(60));
    }

    public function testGetSessionLifetimeReturnsZeroIfProxyDoesNotImplementSessionCookiePersistence(): void
    {
        $this->assertProxyCreated($this->persistence, $this->request);
        $this->initializeProxy();

        $session = new LazySession($this->persistence, $this->request);

        $this->assertSame(0, $session->getSessionLifetime());
    }

    public function testGetSessionLifetimeReturnsValueFromProxy(): void
    {
        $proxy = $this->createMock(Session::class);
        $proxy->method('getSessionLifetime')->willReturn(60);
        $this->persistence->method('initializeSessionFromRequest')->with($this->request)->willReturn($proxy);

        $session = new LazySession($this->persistence, $this->request);

        $this->assertSame(60, $session->getSessionLifetime());
    }

    public function testInitializeIdThrowsNotInitializeableException(): void
    {
        $this->expectException(NotInitializableException::class);
        $this->session->initializeId();
    }

    public function testGenerateIdReturnsId(): void
    {
        $newProxy = $this->createMock(Session::class);
        $newProxy->method('getId')->willReturn('generated-id');
        $proxy = $newProxy;

        $persistence = $this->createMock(SessionInitializationPersistenceInterface::class);
        $persistence->method('initializeId')->with($this->proxy)->willReturn($proxy);

        $this->assertProxyCreated($persistence, $this->request);

        $session = new LazySession($persistence, $this->request);
        $actual  = $session->initializeId();

        $r = new ReflectionProperty($session, 'proxiedSession');
        $r->setAccessible(true);
        $this->assertSame($proxy, $r->getValue($session));
        $this->assertSame('generated-id', $actual);
    }
}
