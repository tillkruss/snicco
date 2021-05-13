<?php


    declare(strict_types = 1);


    namespace WPEmerge\Support;

    use Closure;
    use Contracts\ContainerAdapter;
    use LogicException;
    use mindplay\readable;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use Throwable;
    use WPEmerge\Routing\Delegate;
    use WPEmerge\Traits\ReflectsCallable;

    class Pipeline
    {

        use ReflectsCallable;

        /**
         * The container implementation.
         *
         * @var \Contracts\ContainerAdapter
         */
        private $container;

        /**
         *
         * @var ServerRequestInterface
         */
        private $request;

        /**
         * @var array
         */
        private $middleware = [];

        public function __construct(ContainerAdapter $container)
        {

            $this->container = $container;
        }

        public function send(ServerRequestInterface $request) : Pipeline
        {

            $this->request = $request;

            return $this;
        }

        /**
         * Set the array of middleware.
         *
         * Accepted: function ($request, Closure $next), Middleware::class , [Middleware ,
         * 'config_value'
         *
         * Middleware classes must implement Psr\Http\Server\MiddlewareInterface
         *
         */
        public function through(array $middleware) : Pipeline
        {

            $this->middleware = $this->normalizeMiddleware($middleware);

            return $this;
        }

        private function normalizeMiddleware(array $middleware) : array
        {

            return collect($middleware)
                ->map(function ($middleware) {

                    if ($middleware instanceof Closure) {

                        return new Delegate($middleware);
                    }

                    return $middleware;


                })
                ->map(function ($middleware) {

                    $middleware = Arr::wrap($middleware);

                    if ( $middleware[0] instanceof Closure ) {

                        return $middleware;

                    }

                    if ( ! in_array(MiddlewareInterface::class, class_implements($middleware[0]))) {

                        $type = readable::typeof($middleware);
                        $value = readable::value($middleware);

                        throw new LogicException("Unsupported middleware type: {$type} ({$value})");

                    }

                    return $middleware;


                })
                ->map(function ($middleware) {

                    return $this->getMiddlewareAndParams($middleware);

                })
                ->all();

        }

        /**
         * Run the pipeline with a final destination callback.
         *
         * @param  Closure  $request_handler
         *
         * @return ResponseInterface
         */
        public function then(Closure $request_handler) : ResponseInterface
        {

            $this->middleware[] = [ $request_handler, [] ];

            return $this->run($this->buildMiddlewareStack());


        }

        private function run($stack)
        {

            return $stack->handle($this->request);

        }

        private function buildMiddlewareStack() : RequestHandlerInterface
        {

            return $this->nextMiddleware();

        }

        private function nextMiddleware() : Delegate
        {

            if ($this->middleware === []) {

                return new Delegate(function () {

                    throw new LogicException("Unresolved request: middleware stack exhausted with no result");

                });

            }

            return new Delegate( function (ServerRequestInterface $request ) {

                [ $middleware, $constructor_args ] = array_shift($this->middleware);

                // This is the final request handler passed into then()
                if ( $middleware instanceof Closure ) {

                    return $middleware($request, $this->nextMiddleware());

                }


                if ( $middleware instanceof MiddlewareInterface ) {

                    $response = $middleware->process($request, $this->nextMiddleware());

                    return $this->returnIfValid( $response, $middleware );

                }

                /** @var MiddlewareInterface $middleware_instance */
                $middleware_instance = $this->container->make(
                    $middleware,
                    $this->buildNamedConstructorArgs($middleware, $constructor_args)
                );

                $response = $middleware_instance->process($request, $this->nextMiddleware());

                return $this->returnIfValid($response, $middleware_instance);

            });


        }

        private function returnIfValid($response, $middleware) : ResponseInterface
        {

            if ( ! $response instanceof ResponseInterface) {

                $given = readable::value($response);
                $source = readable::callback($middleware);

                throw new LogicException("invalid middleware result: {$given} returned by: {$source}");

            }

            return $response;

        }

        /**
         *
         * @param  array|string|object  $middleware_blueprint
         *
         * @return array
         */
        private function getMiddlewareAndParams($middleware_blueprint) : array
        {

            if (is_object($middleware_blueprint)) {

                return [$middleware_blueprint, []];

            }

            if (is_string($middleware_blueprint)) {

                return [$middleware_blueprint, []];

            }

            $middleware_class = array_shift($middleware_blueprint);

            $constructor_args = $middleware_blueprint;

            return [$middleware_class, $constructor_args];

        }


    }