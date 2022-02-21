<?php

declare(strict_types=1);

namespace Snicco\Component\HttpRouting\Middleware;

use LogicException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Snicco\Component\HttpRouting\Http\Psr7\Request;
use Snicco\Component\HttpRouting\Http\Psr7\ResponseFactory;
use Snicco\Component\HttpRouting\Http\ResponseUtils;
use Snicco\Component\HttpRouting\Routing\UrlGenerator\UrlGenerator;

use function sprintf;

abstract class Middleware implements MiddlewareInterface
{

    private ContainerInterface $container;
    private ?Request $current_request = null;

    /**
     * @psalm-internal Snicco\Component\HttpRouting
     *
     * @interal
     */
    final public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    final public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $request = Request::fromPsr($request);

        if (!$handler instanceof NextMiddleware) {
            $handler = new NextMiddleware(function (Request $request) use ($handler) {
                return $handler->handle($request);
            });
        }

        $this->current_request = $request;

        return $this->handle($request, $handler);
    }

    abstract protected function handle(Request $request, NextMiddleware $next): ResponseInterface;

    final protected function url(): UrlGenerator
    {
        try {
            /** @var UrlGenerator $url */
            $url = $this->container->get(UrlGenerator::class);
            return $url;
        } catch (ContainerExceptionInterface $e) {
            throw new LogicException(
                "The UrlGenerator is not bound correctly in the psr container.\nMessage: {$e->getMessage()}",
                (int)$e->getCode(),
                $e
            );
        }
    }

    final protected function responseFactory(): ResponseFactory
    {
        try {
            /** @var ResponseFactory $factory */
            $factory = $this->container->get(ResponseFactory::class);
            return $factory;
        } catch (ContainerExceptionInterface $e) {
            throw new LogicException(
                "The ResponseFactory is not bound correctly in the psr container.\nMessage: {$e->getMessage()}",
                (int)$e->getCode(),
                $e
            );
        }
    }

    final protected function respondWith(): ResponseUtils
    {
        return new ResponseUtils(
            $this->url(),
            $this->responseFactory(),
            $this->currentRequest()
        );
    }

    private function currentRequest(): Request
    {
        if (!isset($this->current_request)) {
            throw new RuntimeException(sprintf('current request not set on middleware [%s]', static::class));
        }
        return $this->current_request;
    }

}