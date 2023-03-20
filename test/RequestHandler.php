<?php

declare(strict_types=1);

namespace MezzioTest\Session;

use BadMethodCallException;
use Laminas\Diactoros\Response\TextResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RequestHandler implements RequestHandlerInterface
{
    private ?ServerRequestInterface $received = null;
    private ResponseInterface $defaultResponse;

    public function __construct(
        ResponseInterface|null $defaultResponse = null,
    ) {
        $this->defaultResponse = $defaultResponse ?? new TextResponse('Default Response');
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->received = $request;

        return $this->defaultResponse;
    }

    public function received(): ServerRequestInterface
    {
        if (! $this->received) {
            throw new BadMethodCallException('A request has not yet been received');
        }

        return $this->received;
    }

    public function didExecute(): bool
    {
        return $this->received !== null;
    }
}
