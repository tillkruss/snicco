<?php

declare(strict_types=1);

namespace Snicco\Component\HttpRouting\Tests\Http;

use PHPUnit\Framework\TestCase;
use Snicco\Component\HttpRouting\Http\Psr7\ResponseFactory;
use Snicco\Component\HttpRouting\Tests\helpers\CreateTestPsr17Factories;
use Snicco\Component\HttpRouting\Tests\helpers\CreateUrlGenerator;

final class DelegatedResponseTest extends TestCase
{

    use CreateTestPsr17Factories;
    use CreateUrlGenerator;

    private ResponseFactory $factory;

    /** @test */
    public function test_sendHeaders()
    {
        $response = $this->factory->delegate();
        $this->assertTrue($response->shouldHeadersBeSent());

        $response = $this->factory->delegate(false);
        $this->assertFalse($response->shouldHeadersBeSent());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createResponseFactory($this->createUrlGenerator());
    }

}