<?php

declare(strict_types=1);

namespace Tests\unit\Routing;

use Snicco\Routing\Route;
use PHPUnit\Framework\TestCase;
use Snicco\Routing\RoutingResult;

class RoutingResultTest extends TestCase
{
    
    /** @test */
    public function capturedValuesConvertStringNumbersToIntegers()
    {
        
        $routing_result = new RoutingResult($this->route(), ['foo' => '1']);
        
        $this->assertSame(['foo' => 1], $routing_result->capturedUrlSegmentValues());
        
    }
    
    /** @test */
    public function capturedValuesAreNotCompiledTwice()
    {
        
        $routing_result = new RoutingResult($this->route(), ['foo' => '1']);
        
        $this->assertSame(['foo' => 1], $routing_result->capturedUrlSegmentValues());
        $this->assertSame(['foo' => 1], $routing_result->capturedUrlSegmentValues());
        
    }
    
    /** @test */
    public function integersCanBeValues()
    {
        
        $routing_result = new RoutingResult($this->route(), ['foo' => 1]);
        
        $this->assertSame(['foo' => 1], $routing_result->capturedUrlSegmentValues());
        
    }
    
    protected function tearDown() :void
    {
        \Mockery::close();
        parent::tearDown();
    }
    
    private function route()
    {
        return \Mockery::mock(Route::class);
    }
    
}
