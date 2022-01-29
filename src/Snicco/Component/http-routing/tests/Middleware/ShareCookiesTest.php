<?php

declare(strict_types=1);

namespace Snicco\Component\HttpRouting\Tests\Middleware;

use Snicco\Component\HttpRouting\Http\Cookie;
use Snicco\Component\HttpRouting\Http\Psr7\Response;
use Snicco\Component\HttpRouting\Middleware\ShareCookies;
use Snicco\Component\HttpRouting\Tests\InternalMiddlewareTestCase;

class ShareCookiesTest extends InternalMiddlewareTestCase
{
    
    /** @test */
    public function cookies_sent_by_the_browser_are_shared()
    {
        $response = $this->runMiddleware(
            new ShareCookies(),
            $this->frontendRequest()->withAddedHeader('Cookie', 'foo=bar')
        );
        
        $response->assertNextMiddlewareCalled();
        $this->assertSame('bar', $this->getReceivedRequest()->cookies()->get('foo'));
    }
    
    /** @test */
    public function response_cookies_can_be_added()
    {
        $this->withNextMiddlewareResponse(function (Response $response) {
            return $response->withCookie(new Cookie('foo', 'bar'));
        });
        
        $response = $this->runMiddleware(
            new ShareCookies(),
            $this->frontendRequest()
        );
        
        $response->assertNextMiddlewareCalled();
        $response->psr()->assertHeader(
            'Set-Cookie',
            'foo=bar; path=/; secure; HostOnly; HttpOnly; SameSite=Lax'
        );
    }
    
    /** @test */
    public function multiple_cookies_can_be_added()
    {
        $this->withNextMiddlewareResponse(function (Response $response) {
            $cookie1 = new Cookie('foo', 'bar');
            $cookie2 = new Cookie('baz', 'biz');
            
            return $response->withCookie($cookie1)
                            ->withCookie($cookie2);
        });
        
        $response = $this->runMiddleware(
            new ShareCookies(),
            $this->frontendRequest()
        );
        
        $cookie_header = $response->psr()->getHeader('Set-Cookie');
        
        $this->assertSame(
            'foo=bar; path=/; secure; HostOnly; HttpOnly; SameSite=Lax',
            $cookie_header[0]
        );
        $this->assertSame(
            'baz=biz; path=/; secure; HostOnly; HttpOnly; SameSite=Lax',
            $cookie_header[1]
        );
    }
    
    /** @test */
    public function a_cookie_can_be_deleted()
    {
        $this->withNextMiddlewareResponse(function (Response $response) {
            return $response->withoutCookie('foo');
        });
        
        $response = $this->runMiddleware(
            new ShareCookies(),
            $this->frontendRequest()
        );
        
        $cookie_header = $response->psr()->getHeader('Set-Cookie');
        $this->assertSame(
            "foo=deleted; path=/; expires=Thu, 01-Jan-1970 00:00:01 UTC; secure; HostOnly; HttpOnly; SameSite=Lax",
            $cookie_header[0]
        );
    }
    
}



