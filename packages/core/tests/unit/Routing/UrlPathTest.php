<?php

declare(strict_types=1);

namespace Tests\Core\unit\Routing;

use Generator;
use Snicco\Support\Str;
use Snicco\Core\Routing\UrlPath;
use Tests\Codeception\shared\UnitTest;

class UrlPathTest extends UnitTest
{
    
    /**
     * @dataProvider providePath
     * @test
     */
    public function testFromString($path, $expected)
    {
        $path = UrlPath::fromString($path);
        $this->assertSame($expected, $path->asString());
    }
    
    /**
     * @test
     * @dataProvider providePath
     */
    public function testWithTrailingSlash($path)
    {
        $path = UrlPath::fromString($path);
        $this->assertSame('/foo/', $path->withTrailingSlash()->asString());
    }
    
    /**
     * @test
     * @dataProvider providePath
     */
    public function testWithoutTrailingSlash($path)
    {
        $path = UrlPath::fromString($path);
        $this->assertSame('/foo', $path->withoutTrailingSlash()->asString());
    }
    
    /** @test */
    public function testWithoutTrailingSlashImmutable()
    {
        $path = UrlPath::fromString('foo');
        $path_new = $path->withoutTrailingSlash();
        
        $this->assertNotSame($path_new, $path);
    }
    
    /** @test */
    public function testWithTrailingSlashImmutable()
    {
        $path = UrlPath::fromString('foo');
        $path_new = $path->withTrailingSlash();
        
        $this->assertNotSame($path_new, $path);
    }
    
    /**
     * @test
     * @dataProvider prependProvider
     */
    public function testPrepend($prepend)
    {
        $path = UrlPath::fromString('/foo');
        $this->assertSame('/'.trim($prepend, '/').'/foo', $path->prepend($prepend)->asString());
    }
    
    /**
     * @test
     * @dataProvider prependProvider
     */
    public function testAppend($append_path)
    {
        $expected = Str::endsWith($append_path, '/')
            ? trim($append_path, '/').'/'
            : trim(
                $append_path,
                '/'
            );
        
        $path = UrlPath::fromString('/foo');
        $this->assertSame('/foo/'.$expected, $path->append($append_path)->asString());
    }
    
    public function providePath() :array
    {
        return [
            ['foo', '/foo'],
            ['/foo', '/foo'],
            ['//foo', '/foo'],
            ['/foo/', '/foo/'],
            ['/foo//', '/foo/'],
        ];
    }
    
    public function prependProvider() :Generator
    {
        yield ['foo'];
        yield ['/foo'];
        yield ['//foo'];
        yield ['/foo/'];
        yield ['/foo//'];
    }
    
}