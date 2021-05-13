<?php


    declare(strict_types = 1);


    namespace WPEmerge\Http;

    use Closure;
    use Contracts\ContainerAdapter;
    use Psr\Http\Message\RequestInterface;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use Throwable;

    class MiddlewareStack implements RequestHandlerInterface
    {

        /**
         * @var ContainerAdapter
         */
        private $container;

        /**
         * @var RequestInterface
         */
        private $request;

        /**
         * @var MiddlewareInterface[]
         */
        private $middleware;


        public function __construct( ContainerAdapter $container ) {

            $this->container = $container;

        }


        public function send( RequestInterface $request ) : MiddlewareStack {

            $this->request = $request;

            return $this;

        }

        /**
         * Set the array of middleware
         *
         * @param MiddlewareInterface|MiddlewareInterface[] $middleware
         *
         * @return $this
         */
        public function through( $middleware ) : MiddlewareStack {

            $this->middleware = is_array( $middleware ) ? $middleware : func_get_args();

            return $this;
        }


        /**
         * Run the pipeline with a final destination callback.
         *
         * @param  Closure  $destination
         *
         * @return mixed
         */
        public function then( Closure $destination ) {

            $pipeline = array_reduce(
                array_reverse( $this->middleware ), $this->carry(), $this->prepareDestination( $destination )
            );

            return $pipeline( $this->request );

        }

        /**
         * Run the pipeline and return the result.
         *
         * @return mixed
         */
        public function thenReturn() {

            return $this->then( function ( $passable ) {

                return $passable;

            } );
        }

        /**
         * Get the final piece of the Closure onion.
         *
         * @param  \Closure  $destination
         *
         * @return \Closure
         */
        private function prepareDestination( Closure $destination ) {

            return function ( $passable ) use ( $destination ) {

                try {
                    return $destination( $passable );
                }
                catch ( Throwable $e ) {
                    return $this->handleException( $passable, $e );
                }
            };
        }

        /**
         * Get a Closure that represents a slice of the application onion.
         *
         * @return \Closure
         */
        private function carry() : Closure {

            return function ( $stack, $pipe ) {

                return function ( $passable ) use ( $stack, $pipe ) {

                    try {
                        if ( is_callable( $pipe ) ) {
                            // If the pipe is a callable, then we will call it directly, but otherwise we
                            // will resolve the pipes out of the dependency container and call it with
                            // the appropriate method and arguments, returning the results back out.
                            return $pipe( $passable, $stack );

                        } elseif ( ! is_object( $pipe ) ) {

                            [ $name, $parameters ] = $this->parsePipeArray( $pipe );

                            // If the pipe is a string we will parse the string and resolve the class out
                            // of the dependency injection container. We can then build a callable and
                            // execute the pipe function giving in the parameters that are required.
                            $pipe = $this->container->make( $name );

                            $parameters = array_merge( [ $passable, $stack ], $parameters );

                        } else {
                            // If the pipe is already an object we'll just make a callable and pass it to
                            // the pipe as-is. There is no need to do any extra parsing and formatting
                            // since the object we're given was already a fully instantiated object.
                            $parameters = [ $passable, $stack ];
                        }

                        $carry = method_exists( $pipe, $this->method )
                            ? $pipe->{$this->method}( ...$parameters )
                            : $pipe( ...$parameters );

                        return $this->handleCarry( $carry );
                    }
                    catch ( Throwable $e ) {
                        return $this->handleException( $passable, $e );
                    }
                };
            };
        }

        /**
         * Parse full pipe string to get name and parameters.
         *
         * @param array|string $pipe
         *
         * @return array
         */
        private function parsePipeArray( $pipe ) {

            if ( is_string($pipe) ) {

                return [ $pipe, [] ];

            }

            $middleware_class = array_shift($pipe);

            $parameters = $pipe;

            return [ $middleware_class, $parameters ];
        }





        /**
         * Handle the value returned from each pipe before passing it to the next.
         *
         * @param  mixed  $carry
         *
         * @return mixed
         */
        private function handleCarry( $carry ) {

            return $carry;
        }

        /**
         * Handle the given exception.
         *
         * @param  mixed  $passable
         * @param  Throwable  $e
         *
         * @return mixed
         *
         * @throws Throwable
         */
        private function handleException( $passable, Throwable $e ) {

            throw $e;
        }


        public function handle(ServerRequestInterface $request) : ResponseInterface
        {


        }

    }