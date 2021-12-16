<?php

declare(strict_types=1);

namespace Snicco\Core\Middleware;

use Snicco\Core\Routing\Pipeline;
use Snicco\Core\Http\MethodField;
use Snicco\Core\Contracts\MagicLink;
use Snicco\Core\Http\ResponseEmitter;
use Snicco\Core\Contracts\ServiceProvider;
use Snicco\Core\Factories\MiddlewareFactory;
use Snicco\Core\Middleware\Core\RouteRunner;
use Psr\Http\Message\StreamFactoryInterface;
use Snicco\Core\Factories\RouteActionFactory;
use Snicco\Core\Middleware\Core\ShareCookies;
use Snicco\Core\Middleware\Core\MethodOverride;
use Snicco\Core\Factories\RouteConditionFactory;
use Snicco\Core\Middleware\Core\RoutingMiddleware;
use Snicco\Core\Contracts\RouteCollectionInterface;
use Snicco\Core\Middleware\Core\SetRequestAttributes;
use Snicco\Core\Middleware\Core\OpenRedirectProtection;
use Snicco\Core\Middleware\Core\OutputBufferMiddleware;
use Snicco\Core\Middleware\Core\EvaluateResponseMiddleware;
use Snicco\Core\Middleware\Core\AllowMatchingAdminAndAjaxRoutes;

class MiddlewareServiceProvider extends ServiceProvider
{
    
    public function register() :void
    {
        $this->bindConfig();
        
        $this->bindMiddlewareStack();
        
        $this->bindEvaluateResponseMiddleware();
        
        $this->bindTrailingSlash();
        
        $this->bindWww();
        
        $this->bindSecureMiddleware();
        
        $this->bindOpenRedirectProtection();
        
        $this->bindValidateSignature();
        
        $this->bindOutputBufferMiddleware();
        
        $this->bindMiddlewareFactory();
        
        $this->bindRoutingMiddleware();
        
        $this->bindRouteRunnerMiddleware();
        
        $this->bindSetRequestAttributes();
        
        $this->bindMethodOverride();
        
        $this->bindShareCookies();
        
        $this->bindRoutingPathSuffixMiddleware();
    }
    
    function bootstrap() :void
    {
        //
    }
    
    private function bindConfig()
    {
        $this->config->extend('middleware.aliases', [
            
            'auth' => Authenticate::class,
            'guest' => RedirectIfAuthenticated::class,
            'can' => Authorize::class,
            'json' => JsonPayload::class,
            'robots' => NoRobots::class,
            'secure' => Secure::class,
            'signed' => ValidateSignature::class,
        
        ]);
        
        $this->config->extend('middleware.groups', [
            
            'global' => [],
            'web' => [],
            'ajax' => [],
            'admin' => [],
            'api' => [],
        
        ]);
        
        $this->config->extend(
            'middleware.priority',
            [Secure::class, Www::class, TrailingSlash::class,]
        );
        $this->config->extend('middleware.always_run_core_groups', false);
    }
    
    private function bindMiddlewareStack()
    {
        $this->container->singleton(MiddlewareStack::class, function () {
            $stack = new MiddlewareStack(
                $this->config->get('middleware.always_run_core_groups', false)
            );
            
            if ($this->config->get('middleware.disabled', false)) {
                $stack->disableAllMiddleware();
                return $stack;
            }
            
            foreach ($this->config->get('middleware.groups') as $name => $middleware) {
                $stack->withMiddlewareGroup($name, $middleware);
            }
            
            $stack->middlewarePriority($this->config->get('middleware.priority', []));
            $stack->middlewareAliases($this->config->get('middleware.aliases', []));
            
            return $stack;
        });
    }
    
    private function bindEvaluateResponseMiddleware()
    {
        $this->container->singleton(EvaluateResponseMiddleware::class, function () {
            return new EvaluateResponseMiddleware(
                $this->config->get('routing.must_match_web_routes', false)
            );
        });
    }
    
    private function bindTrailingSlash()
    {
        $this->container->singleton(TrailingSlash::class, fn() => new TrailingSlash(
            $this->withSlashes()
        ));
    }
    
    private function bindWww()
    {
        $this->container->singleton(Www::class, fn() => new Www(
            $this->siteUrl()
        ));
    }
    
    private function bindSecureMiddleware()
    {
        $this->container->singleton(Secure::class, fn() => new Secure(
            ($this->app->isLocal() || $this->app->isRunningUnitTest())
        ));
    }
    
    private function bindOpenRedirectProtection()
    {
        $this->container->singleton(
            OpenRedirectProtection::class,
            fn() => new OpenRedirectProtection(
                $this->siteUrl()
            )
        );
    }
    
    private function bindOutputBufferMiddleware()
    {
        $this->container->singleton(OutputBufferMiddleware::class, function () {
            return new OutputBufferMiddleware(
                $this->container->get(ResponseEmitter::class),
                $this->container->get(StreamFactoryInterface::class)
            );
        });
    }
    
    private function bindMiddlewareFactory()
    {
        $this->container->singleton(MiddlewareFactory::class, function () {
            return new MiddlewareFactory($this->container);
        });
    }
    
    private function bindRouteRunnerMiddleware()
    {
        $this->container->singleton(RouteRunner::class, function () {
            return new RouteRunner(
                $this->container[Pipeline::class],
                $this->container[MiddlewareStack::class],
                $this->container[RouteActionFactory::class]
            );
        });
    }
    
    private function bindSetRequestAttributes()
    {
        $this->container->singleton(SetRequestAttributes::class, function () {
            return new SetRequestAttributes();
        });
    }
    
    private function bindMethodOverride()
    {
        $this->container->singleton(MethodOverride::class, function () {
            return new MethodOverride(
                $this->container[MethodField::class]
            );
        });
    }
    
    private function bindShareCookies()
    {
        $this->container->singleton(ShareCookies::class, function () {
            return new ShareCookies();
        });
    }
    
    private function bindRoutingPathSuffixMiddleware()
    {
        $this->container->singleton(AllowMatchingAdminAndAjaxRoutes::class, function () {
            return new AllowMatchingAdminAndAjaxRoutes();
        });
    }
    
    private function bindRoutingMiddleware()
    {
        $this->container->singleton(RoutingMiddleware::class, function () {
            return new RoutingMiddleware(
                $this->container[RouteCollectionInterface::class],
                $this->container[RouteConditionFactory::class]
            );
        });
    }
    
    private function bindValidateSignature()
    {
        $this->container->singleton(ValidateSignature::class, function () {
            return new ValidateSignature($this->container[MagicLink::class]);
        });
    }
    
}