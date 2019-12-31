# mezzio-session

[![Build Status](https://travis-ci.org/mezzio/mezzio-session.svg?branch=master)](https://travis-ci.org/mezzio/mezzio-session)
[![Coverage Status](https://coveralls.io/repos/github/mezzio/mezzio-session/badge.svg?branch=master)](https://coveralls.io/github/mezzio/mezzio-session?branch=master)

This library provides session handling middleware for PSR-7 applications, using
an adapter-based approach that will allow usage of ext-session, JWT, or other
approaches.

> ### NOT YET RELEASED
>
> This package is an experiment, and undergoing heavy architectural design
> currently. As such, it is not yet on Packagist. You will need to add a
> repository to your `composer.json` if you wish to use it at this time.
>
> Use at your own risk!

## Installation

Run the following to install this library:

```bash
$ composer require mezzio/mezzio-session
```

## Documentation

Documentation is [in the doc tree](docs/book/), and can be compiled using [mkdocs](https://www.mkdocs.org):

```bash
$ mkdocs build
```

~~You may also [browse the documentation online](https://docs.mezzio.dev/mezzio-session/).~~

### Basic usage

The default use case is to use the shipped `PhpSessionPersistence` adapter with
the shipped `SessionMiddleware`. As such, you can pipe it to your application:

```php
$app->pipe(SessionMiddleware::class);
```

You can also pipe it within routed middleware:

```php
$app->post('/contact/process', [
    \Mezzio\Session\SessionMiddleware::class,
    \App\Contact\ProcessHandler::class
]);
```

Once the middleware is in place, you can access the session container from your
other middleware via the request attribute
`Mezzio\Session\SessionMiddleare::SESSION_ATTRIBUTE`:

```php
use Mezzio\Session\SessionMiddleware;

$session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
$session->get('some-key');
$session->unset('some-key');
$session->set('some-key', $value);
```

### Custom persistence

To use custom persistence — e.g., a JWT-based approach — implement
`Mezzio\Session\SessionPersistenceInterface`:

```php
namespace Mezzio\Session;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface SessionPersistenceInterface
{
    /**
     * Initialize the session data instance associated with the persistence
     * engine based on the current request.
     */
    public function initializeSessionFromRequest(ServerRequestInterface $request) : SessionInterface;

    /**
     * Persist the session data instance.
     *
     * Persists the session data, returning a response instance with any
     * artifacts required to return to the client.
     */
    public function persistSession(SessionInterface $session, ResponseInterface $response) : ResponseInterface;
}
```

Once implemented, create an alternate factory for the
`Mezzio\Session\SessionMiddleware` service:

```php
namespace App\Session;

use Psr\Container\ContainerInterface;
use Mezzio\Session\SessionMiddleware;

class SessionMiddlewareFactory
{
    public function __invoke(ContainerInterface $container) : SessionMiddleware
    {
        // Where App\Session\JwtPersistence implements SessionPersistenceInterface
        return new SessionMiddleware($container->get(JwtPersistence::class));
    }
}
```

Once the factory exists, configure your application to use this factory; this is
typically done via an override in your `config/autoload/dependencies.global.php`
file:

```php
return [
    'dependencies' => [
        'factories' => [
            \Mezzio\Session\SessionMiddleware::class => App\Session\SessionMiddlewareFactory::class,
        ],
    ],
];
```
