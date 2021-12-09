<?php

declare(strict_types=1);

namespace Snicco\Core\Application;

use LogicException;
use BadMethodCallException;
use Snicco\Core\Shared\ContainerAdapter;
use Snicco\EventDispatcher\Contracts\Dispatcher;
use Snicco\Core\ExceptionHandling\Exceptions\ConfigurationException;

/**
 * @mixin ApplicationMixin
 */
trait ApplicationTrait
{
    
    private static ?Application $instance = null;
    
    public static function make(string $base_path, ContainerAdapter $container) :Application
    {
        if ( ! is_null(static::$instance)) {
            $class = static::class;
            throw new LogicException("Application already created for class [$class].");
        }
        
        static::setApplication(
            Application::create($base_path, $container)
        );
        
        $app = static::$instance;
        $app->container()->primitive(ApplicationTrait::class, static::class);
        
        return $app;
    }
    
    public static function setApplication(?Application $application)
    {
        static::$instance = $application;
    }
    
    /**
     * Invoke any matching instance method for the static method being called.
     *
     * @param  string  $method
     * @param  array  $parameters
     *
     * @return mixed
     * @throws ConfigurationException
     */
    public static function __callStatic(string $method, array $parameters)
    {
        $application = static::$instance;
        $callable = [$application, $method];
        
        if ( ! $application) {
            throw new ConfigurationException(
                'Application instance not created in '.static::class.'. '.
                'Did you miss to call '.static::class.'::make()?'
            );
        }
        
        if ( ! is_callable($callable)) {
            throw new BadMethodCallException(
                'Method '.get_class($application).'::'.$method.'() does not exist.'
            );
        }
        
        return call_user_func_array($callable, $parameters);
    }
    
    public static function events() :Dispatcher
    {
        return static::$instance[Dispatcher::class];
    }
    
}
