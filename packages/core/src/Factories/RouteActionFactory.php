<?php

declare(strict_types=1);

namespace Snicco\Core\Factories;

use Closure;
use RuntimeException;
use Snicco\Core\Support\Reflector;
use Snicco\Core\Routing\ClosureAction;
use Snicco\Core\Contracts\RouteAction;
use Snicco\Core\Routing\ControllerAction;
use Snicco\Core\Support\ReflectionDependencies;

class RouteActionFactory extends AbstractFactory
{
    
    public function create($raw_action, $namespace = '') :RouteAction
    {
        if ($raw_action instanceof Closure) {
            return new ClosureAction(
                $raw_action,
                new ReflectionDependencies($this->container)
            );
        }
        
        if ( ! Reflector::isCallable($raw_action) && ! empty($namespace)) {
            return $this->createControllerAction(
                $namespace.'\\'.$raw_action,
            );
        }
        
        return $this->createControllerAction($raw_action);
    }
    
    /**
     * @param  string|array  $handler
     *
     * @throws RuntimeException
     */
    private function createControllerAction($handler) :ControllerAction
    {
        $handler = $this->normalizeInput($handler);
        
        if ($namespaced_handler = $this->checkIfCallable($handler)) {
            return new ControllerAction(
                $namespaced_handler,
                $this->container,
                new ReflectionDependencies($this->container)
            );
        }
        
        $this->fail($handler[0], $handler[1]);
    }
    
}