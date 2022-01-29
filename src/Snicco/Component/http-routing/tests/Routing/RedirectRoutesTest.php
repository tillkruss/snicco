<?php

declare(strict_types=1);

namespace Snicco\Component\HttpRouting\Tests\Routing;

use Snicco\Component\HttpRouting\Tests\HttpRunnerTestCase;
use Snicco\Component\HttpRouting\Routing\Controller\RedirectController;
use Snicco\Component\HttpRouting\Tests\fixtures\Controller\RoutingTestController;

class RedirectRoutesTest extends HttpRunnerTestCase
{
    
    protected function setUp() :void
    {
        parent::setUp();
        $this->container->instance(
            RedirectController::class,
            new RedirectController()
        );
    }
    
    /** @test */
    public function a_redirect_route_can_be_created()
    {
        $this->routeConfigurator()->redirect('/foo', '/bar', 307, ['baz' => 'biz']);
        
        $request = $this->frontendRequest('/foo');
        
        $response = $this->runKernel($request);
        
        $response->assertStatus(307)
                 ->assertLocation('/bar?baz=biz');
    }
    
    /** @test */
    public function a_permanent_redirect_can_be_created()
    {
        $this->routeConfigurator()->permanentRedirect('/foo', '/bar', ['baz' => 'biz']);
        
        $request = $this->frontendRequest('/foo');
        
        $response = $this->runKernel($request);
        
        $response->assertStatus(301)->assertLocation('/bar?baz=biz');
    }
    
    /** @test */
    public function a_temporary_redirect_can_be_created()
    {
        $this->routeConfigurator()->temporaryRedirect('/foo', '/bar', ['baz' => 'biz']);
        
        $request = $this->frontendRequest('/foo');
        
        $response = $this->runKernel($request);
        
        $response->assertStatus(307)->assertLocation('/bar?baz=biz');
    }
    
    /** @test */
    public function a_redirect_to_an_external_url_can_be_created()
    {
        $this->routeConfigurator()->redirectAway('/foo', 'https://foobar.com', 301);
        
        $request = $this->frontendRequest('/foo');
        $this->assertResponseBody('', $request);
        
        $response = $this->runKernel($request);
        
        $response->assertRedirect('https://foobar.com', 301);
    }
    
    /** @test */
    public function a_redirect_to_a_route_can_be_created()
    {
        $this->routeConfigurator()->get('route1', '/base/{param}');
        $this->routeConfigurator()->redirectToRoute('/foo', 'route1', ['param' => 'baz'], 303);
        
        $request = $this->frontendRequest('/foo');
        
        $response = $this->runKernel($request);
        
        $response->assertStatus(303);
        $response->assertLocation('/base/baz');
    }
    
    /** @test */
    public function regex_based_redirects_works()
    {
        $this->routeConfigurator()->redirect('base/{slug}', 'base/new')
             ->requireOneOf('slug', ['foo', 'bar']);
        
        $this->routeConfigurator()->get('r1', 'base/biz', RoutingTestController::class);
        
        $request = $this->frontendRequest('base/foo');
        $response = $this->runKernel($request);
        $response->assertRedirect('/base/new');
        
        $request = $this->frontendRequest('base/bar');
        $response = $this->runKernel($request);
        $response->assertRedirect('/base/new');
        
        $request = $this->frontendRequest('base/biz');
        $response = $this->runKernel($request);
        $response->assertOk()->assertSeeText(RoutingTestController::static);
    }
    
}