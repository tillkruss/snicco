<?php

declare(strict_types=1);

namespace Snicco\ViewBundle;

use Closure;
use RuntimeException;
use Snicco\Component\Core\DIContainer;
use Snicco\Component\Templating\ViewComposer\ViewComposer;
use Snicco\Component\Templating\ViewComposer\ViewComposerFactory;
use Snicco\Component\Templating\ViewComposer\ClosureViewComposer;

class DependencyInjectionViewComposerFactory implements ViewComposerFactory
{
    
    /**
     * An array of fully qualified namespaces that will be prepended to the composer class.
     *
     * @var string[]
     */
    private $namespaces;
    
    /**
     * @var DIContainer
     */
    private $container;
    
    public function __construct(DIContainer $container, array $namespaces = [])
    {
        $this->namespaces = $namespaces;
        $this->container = $container;
    }
    
    /**
     * @param  string|Closure  $composer  A class name if a string is passed.
     *
     * @return ViewComposer
     */
    public function create($composer) :ViewComposer
    {
        if ($composer instanceof Closure) {
            return $this->composerFromClosure($composer);
        }
        
        if (class_exists($composer)) {
            return $this->composerClass($composer);
        }
        
        foreach ($this->namespaces as $namespace) {
            $class = trim($namespace, '\\').'\\'.$composer;
            if (class_exists($class)) {
                return $this->composerClass($class);
            }
        }
        
        throw new RuntimeException("Composer [$composer] could not be created.");
    }
    
    private function composerFromClosure(Closure $composer_closure) :ViewComposer
    {
        return new ClosureViewComposer($composer_closure);
    }
    
    private function composerClass($composer) :ViewComposer
    {
        $instance = $this->container->get($composer);
        if ( ! $this->container->has($composer)) {
            $this->container->instance($composer, $instance);
        }
        return $instance;
    }
    
}