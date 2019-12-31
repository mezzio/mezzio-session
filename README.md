# mezzio-session

[![Build Status](https://travis-ci.org/mezzio/mezzio-session.svg?branch=master)](https://travis-ci.org/mezzio/mezzio-session)
[![Coverage Status](https://coveralls.io/repos/github/mezzio/mezzio-session/badge.svg?branch=master)](https://coveralls.io/github/mezzio/mezzio-session?branch=master)

This library provides session handling middleware for PSR-7 applications, using
an adapter-based approach that will allow usage of ext-session, JWT, or other
approaches.

## Installation

Run the following to install this library:

```bash
$ composer require mezzio/mezzio-session
```

However, the package is not immediately useful unless you have a persistence
adapter. If you are okay with using ext-session, you can install the following
package as well:

```bash
$ composer require mezzio/mezzio-session-ext
```

## Documentation

Documentation is [in the doc tree](docs/book/), and can be compiled using [mkdocs](https://www.mkdocs.org):

```bash
$ mkdocs build
```

You may also [browse the documentation online](https://docs.mezzio.dev/mezzio-session/).
