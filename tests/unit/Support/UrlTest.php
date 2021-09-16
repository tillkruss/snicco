<?php

declare(strict_types=1);

namespace Tests\unit\Support;

use Snicco\Support\Url;
use PHPUnit\Framework\TestCase;

class UrlTest extends TestCase
{
    
    /** @test */
    public function rebuildQueryValidQuery()
    {
        
        $search = rawurlencode('foo bar');
        $result = Url::rebuild("https://foobar.com/foo/bar?search=$search");
        $this->assertSame("https://foobar.com/foo/bar?search=foo%20bar", $result);
        
    }
    
    /** @test */
    public function rebuildQueryInvalidQuery()
    {
        
        $search = 'foo bar';
        $result = Url::rebuild("https://foobar.com/foo/bar?search=$search");
        $this->assertSame("https://foobar.com/foo/bar?search=foo%20bar", $result);
        
    }
    
}