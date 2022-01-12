<?php

declare(strict_types=1);

namespace Tests\Core\unit\Support;

use stdClass;
use InvalidArgumentException;
use Snicco\Core\Support\PHPCacheFile;
use Tests\Codeception\shared\UnitTest;

final class PHPCacheFileTest extends UnitTest
{
    
    private string $file;
    
    protected function setUp() :void
    {
        parent::setUp();
        $this->file = __DIR__.'/foo.php';
        $this->assertFalse(is_file($this->file));
    }
    
    protected function tearDown() :void
    {
        parent::tearDown();
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }
    
    /** @test */
    public function test_exception_if_path_is_not_php()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be [.php].');
        
        new PHPCacheFile(__DIR__, 'foo.js');
    }
    
    /** @test */
    public function test_isCreated()
    {
        $cache = new PHPCacheFile(__DIR__, 'foo.php');
        $this->assertFalse($cache->isCreated());
        touch($this->file);
        $this->assertTrue($cache->isCreated());
    }
    
    /** @test */
    public function test_realpath()
    {
        $cache = new PHPCacheFile(__DIR__, 'foo.php');
        $this->assertSame(__DIR__.'/foo.php', $cache->realPath());
    }
    
    /** @test */
    public function test_require()
    {
        $class = new stdClass();
        $class->foo = 'bar';
        
        file_put_contents(
            $this->file,
            '<?php return '.var_export($class, true).';'
        );
        
        $cache_file = new PHPCacheFile(__DIR__, 'foo.php');
        
        $res = $cache_file->require();
        
        $this->assertEquals($res, $class);
    }
    
}