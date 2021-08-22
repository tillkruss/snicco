<?php

declare(strict_types=1);

namespace Snicco\Middleware\Core;

use Throwable;
use Snicco\Http\Delegate;
use Snicco\Http\Psr7\Request;
use Snicco\Contracts\Middleware;
use Snicco\Contracts\ExceptionHandler;
use Psr\Http\Message\ResponseInterface;

class ErrorHandlerMiddleware extends Middleware
{
    
    private ExceptionHandler $error_handler;
    
    public function __construct(ExceptionHandler $error_handler)
    {
        $this->error_handler = $error_handler;
    }
    
    public function handle(Request $request, Delegate $next) :ResponseInterface
    {
        
        try {
            
            return $next($request);
            
        } catch (Throwable $e) {
            
            return $this->error_handler->toHttpResponse($e, $request);
            
        }
        
    }
    
}