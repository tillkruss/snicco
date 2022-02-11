<?php

declare(strict_types=1);


namespace Snicco\Component\HttpRouting\Routing;

use FastRoute\BadRouteException;
use FastRoute\DataGenerator\GroupCountBased as DataGenerator;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std as RouteParser;
use Psr\Container\ContainerInterface;
use Snicco\Component\HttpRouting\Routing\Admin\AdminArea;
use Snicco\Component\HttpRouting\Routing\Admin\AdminMenu;
use Snicco\Component\HttpRouting\Routing\Admin\WPAdminArea;
use Snicco\Component\HttpRouting\Routing\Cache\NullCache;
use Snicco\Component\HttpRouting\Routing\Cache\RouteCache;
use Snicco\Component\HttpRouting\Routing\Condition\RouteConditionFactory;
use Snicco\Component\HttpRouting\Routing\Exception\BadRouteConfiguration;
use Snicco\Component\HttpRouting\Routing\Route\CachedRouteCollection;
use Snicco\Component\HttpRouting\Routing\Route\Routes;
use Snicco\Component\HttpRouting\Routing\RouteLoader\RouteLoader;
use Snicco\Component\HttpRouting\Routing\RoutingConfigurator\Configurator;
use Snicco\Component\HttpRouting\Routing\UrlGenerator\RFC3986Encoder;
use Snicco\Component\HttpRouting\Routing\UrlGenerator\UrlEncoder;
use Snicco\Component\HttpRouting\Routing\UrlGenerator\UrlGenerationContext;
use Snicco\Component\HttpRouting\Routing\UrlGenerator\UrlGenerator;
use Snicco\Component\HttpRouting\Routing\UrlGenerator\UrlGeneratorInterface;
use Snicco\Component\HttpRouting\Routing\UrlMatcher\AdminRouteMatcher;
use Snicco\Component\HttpRouting\Routing\UrlMatcher\FastRouteDispatcher;
use Snicco\Component\HttpRouting\Routing\UrlMatcher\FastRouteSyntaxConverter;
use Snicco\Component\HttpRouting\Routing\UrlMatcher\UrlMatcher;

use function serialize;

final class Routing
{

    private ContainerInterface $psr_container;
    private UrlGenerationContext $context;
    private AdminArea $admin_area;
    private UrlEncoder $url_encoder;
    private RouteCache $cache;
    private RouteLoader $route_loader;
    private ?Configurator $routing_configurator = null;

    /**
     * @var ?array{url_matcher:array, route_collection:array<string,string>}
     */
    private ?array $route_data = null;

    public function __construct(
        ContainerInterface $psr_container,
        UrlGenerationContext $context,
        RouteLoader $loader,
        ?RouteCache $cache = null,
        ?AdminArea $admin_area = null,
        ?UrlEncoder $url_encoder = null
    ) {
        $this->psr_container = $psr_container;
        $this->context = $context;
        $this->route_loader = $loader;
        $this->admin_area = $admin_area ?: WPAdminArea::fromDefaults();
        $this->url_encoder = $url_encoder ?: new RFC3986Encoder();
        $this->cache = $cache ?: new NullCache();
    }

    public function urlMatcher(): UrlMatcher
    {
        return new AdminRouteMatcher(
            new FastRouteDispatcher(
                $this->routes(),
                $this->routeData()['url_matcher'],
                new RouteConditionFactory($this->psr_container)
            ), $this->admin_area
        );
    }

    public function urlGenerator(): UrlGeneratorInterface
    {
        return new UrlGenerator(
            $this->routes(),
            $this->context,
            $this->admin_area,
            $this->url_encoder,
        );
    }

    public function routes(): Routes
    {
        return new CachedRouteCollection($this->routeData()['route_collection']);
    }

    public function adminMenu(): AdminMenu
    {
        return $this->routingConfigurator();
    }

    private function routingConfigurator(): Configurator
    {
        if (!isset($this->routing_configurator)) {
            $this->routing_configurator = new Configurator(
                $this->admin_area->urlPrefix(),
            );
        }
        return $this->routing_configurator;
    }

    /**
     * @return array{url_matcher: array, route_collection: array<string,string>}
     */
    private function routeData(): array
    {
        if (!isset($this->route_data)) {
            $data = $this->cache->get(function () {
                return $this->loadRoutes();
            });

            $this->route_data = $data;
        }

        return $this->route_data;
    }

    /**
     * @return array{url_matcher: array, route_collection: array<string,string>}
     */
    private function loadRoutes(): array
    {
        $configurator = $this->routingConfigurator();
        $this->route_loader->loadWebRoutes($configurator);
        $this->route_loader->loadAdminRoutes($configurator);

        $routes = $configurator->configuredRoutes();

        $collector = new RouteCollector(new RouteParser(), new DataGenerator());
        $syntax = new FastRouteSyntaxConverter();

        $collection = [];

        foreach ($routes as $route) {
            $collection[$route->getName()] = serialize($route);
            $path = $syntax->convert($route);
            try {
                $collector->addRoute($route->getMethods(), $path, $route->getName());
            } catch (BadRouteException $e) {
                throw BadRouteConfiguration::fromPrevious($e);
            }
        }

        return [
            'route_collection' => $collection,
            'url_matcher' => $collector->getData()
        ];
    }

}