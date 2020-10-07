# Session Middleware

mezzio-session provides middleware consuming
[PSR-7](http://www.php-fig.org/psr/psr-7/) HTTP message instances, via
implementation of [PSR-15](https://www.php-fig.org/psr/psr-15/)
interfaces.

This middleware composes a [persistence](persistence.md) instance, and uses that
in order to generate a session container, which it pushes into the request it
delegates to the next middleware. Once a response is returned, it uses the
persistence instance to persist the session data and provide information back to
the client.

The above two paragraphs are longer than the body of the middleware
implementation:

```php
namespace Mezzio\Session;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SessionMiddleware implements MiddlewareInterface
{
    public const SESSION_ATTRIBUTE = 'session';

    private $persistence;

    public function __construct(SessionPersistenceInterface $persistence)
    {
        $this->persistence = $persistence;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $session = new LazySession($this->persistence, $request);
        $response = $handler->handle(
            $request
                ->withAttribute(self::SESSION_ATTRIBUTE, $session)
                ->withAttribute(SessionInterface::class, $session)
        );
        return $this->persistence->persistSession($session, $response);
    }
}
```

## Configuration

This package provides a factory for `Mezzio\Session\SessionMiddleware`
via `Mezzio\Session\SessionMiddlewareFactory`; this factory is
auto-wired if you are using Mezzio and the laminas-component-installer Composer
plugin. If not, you will need to wire these into your application.

The factory depends on one service: `Mezzio\Session\SessionPersistenceInterface`.
You will need to either wire in your persistence implementation of choice, or
have the package providing it do so for you.

## Adding the middleware to your application

You may pipe this middleware anywhere in your application. If you want to have
it available anywhere, pipe it early in your application, prior to any routing.
As an example, within Mezzio, you could pipe it in the `config/pipeline.php`
file:

```php
$app->pipe(\Mezzio\Session\SessionMiddleware::class);
$app->pipe(\Mezzio\Router\Middleware\RouteMiddleware::class);
```

This will generally be an inexpensive operation; since the middleware uses a
`LazySession` instance, unless your persistence implementation does any work in
its constructor, the cost is just that of instantiating a few objects.

However, it's often useful to specifically include such middleware directly in
the routed middleware pipelines, to ensure other developers are aware of its
presence in that route's workflow.

Within Mezzio, you can do this when routing, in your `config/routes.php`
file, or within a [delegator factory](https://docs.mezzio.dev/mezzio/cookbook/autowiring-routes-and-pipelines/#delegator-factories):

```php
$app->post('/login', [
    \Mezzio\Session\SessionMiddleware::class,
    \User\Middleware\LoginHandler::class
]);
```
