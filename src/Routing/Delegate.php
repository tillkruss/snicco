<?php


    declare(strict_types = 1);


    namespace WPEmerge\Routing;

    use mindplay\readable;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;

    /**
     * PSR-15 delegate wrapper for internal callbacks generated by {@see Dispatcher} during dispatch.
     */
    class Delegate implements RequestHandlerInterface, MiddlewareInterface
    {

        /**
         * @var callable
         */
        private $callback;

        /**
         * @param  callable  $callback  function (RequestInterface $request) : ResponseInterface
         */
        public function __construct(callable $callback)
        {

            $this->callback = $callback;
        }

        /**
         * Dispatch the next available middleware and return the response.
         *
         * @param  ServerRequestInterface  $request
         *
         * @return ResponseInterface
         */
        public function handle(ServerRequestInterface $request) : ResponseInterface
        {

            $response = ($this->callback)($request);


            if ( ! $response instanceof ResponseInterface) {

                $given = readable::value($response);
                $source = readable::callback($this);

                throw new \LogicException("invalid middleware result: {$given} returned by: {$source}");

            }

            return $response;

        }

        /**
         * Dispatch the next available middleware and return the response.
         *
         * This method duplicates `handle()` to provide support for `callable` middleware.
         *
         * @param  ServerRequestInterface  $request
         *
         * @return ResponseInterface
         */
        public function __invoke(ServerRequestInterface $request)
        {

            return ($this->callback)($request);

        }

        public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
        {
            return $this->handle($request);

        }

    }