<?php

declare(strict_types=1);

namespace Snicco\Component\Psr7ErrorHandler\Tests;

use Throwable;
use RuntimeException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Snicco\Component\Psr7ErrorHandler\UserFacing;
use Snicco\Component\Psr7ErrorHandler\HttpException;
use Snicco\Component\Psr7ErrorHandler\ExceptionIdentifier;
use Snicco\Component\Psr7ErrorHandler\ExceptionTransformer;
use Snicco\Component\Psr7ErrorHandler\Identifier\SplHashIdentifier;
use Snicco\Component\Psr7ErrorHandler\Information\ExceptionInformation;
use Snicco\Component\Psr7ErrorHandler\Information\HttpInformationProvider;

final class HttpInformationProviderTest extends TestCase
{
    
    /** @test */
    public function http_exceptions_use_the_correct_title_and_status_code()
    {
        $provider = new HttpInformationProvider([
            404 => [
                'title' => 'Not Found',
                'details' => 'The requested resource could not be found but may be available again in the future.',
            ],
            500 => [
                'title' => 'Internal Server Error',
                'details' => 'no details for you.',
            ],
        ], new StubIdentifier('foobar_exception'));
        
        $e = new HttpException(404, 'secret stuff here');
        
        $information = $provider->provideFor($e);
        
        $this->assertInstanceOf(ExceptionInformation::class, $information);
        $this->assertEquals(404, $information->statusCode());
        $this->assertEquals('foobar_exception', $information->identifier());
        $this->assertSame($e, $information->originalException());
        $this->assertSame('Not Found', $information->title());
        $this->assertSame(
            'The requested resource could not be found but may be available again in the future.',
            $information->safeDetails()
        );
        $this->assertSame($e, $information->originalException());
        $this->assertSame($e, $information->transformedException());
    }
    
    /** @test */
    public function an_exception_is_thrown_if_the_500_message_data_is_not_provided()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Data for the 500 status code must be provided.");
        
        $provider = new HttpInformationProvider([
            404 => [
                'title' => 'Not Found',
                'details' => 'The requested resource could not be found but may be available again in the future.',
            ],
        ], new StubIdentifier('foo'));
    }
    
    /** @test */
    public function exceptions_can_be_transformed()
    {
        $provider = $this->newProvider([
            401 => ['title' => 'Unauthorized', 'details' => 'You need to log-in first.'],
        ], new StubIdentifier('foobar_e'), new RuntimeToAuthTransformer());
        
        $e = new RuntimeException('transform_me');
        
        $information = $provider->provideFor($e);
        
        $this->assertEquals(401, $information->statusCode());
        $this->assertEquals('foobar_e', $information->identifier());
        $this->assertSame($e, $information->originalException());
        $this->assertSame('Unauthorized', $information->title());
        $this->assertSame(
            'You need to log-in first.',
            $information->safeDetails()
        );
        $this->assertSame($e, $information->originalException());
        
        $transformed = $information->transformedException();
        
        $this->assertNotSame($e, $transformed);
        $this->assertInstanceOf(HttpException::class, $transformed);
        $this->assertSame(401, $transformed->statusCode());
    }
    
    /** @test */
    public function exceptions_will_only_be_transformed_if_a_transformer_decides_so()
    {
        $provider = $this->newProvider([
            401 => ['title' => 'Unauthorized', 'details' => 'You need to log-in first.'],
        ], new StubIdentifier('foo_id'), new RuntimeToAuthTransformer());
        
        $e = new RuntimeException('dont_transform_me');
        
        $information = $provider->provideFor($e);
        
        $this->assertEquals(500, $information->statusCode());
        $this->assertEquals('foo_id', $information->identifier());
        $this->assertSame($e, $information->originalException());
        $this->assertSame('Internal Server Error', $information->title());
    }
    
    /** @test */
    public function multiple_transformers_can_be_used_in_the_same_order_they_were_run()
    {
        $provider = $this->newProvider([
            401 => ['title' => 'Unauthorized', 'details' => 'You need to log-in first.'],
            403 => ['title' => 'Forbidden', 'details' => 'You cant do this.'],
        ], new StubIdentifier('foo'), new RuntimeToAuthTransformer(), new LastTransformer());
        
        $e = new RuntimeException('transform_me');
        
        $information = $provider->provideFor($e);
        
        $this->assertSame(403, $information->statusCode());
        $this->assertSame('foo', $information->identifier());
        $this->assertSame($e, $information->originalException());
        $this->assertSame('Forbidden', $information->title());
        $this->assertSame('You cant do this.', $information->safeDetails());
    }
    
    /** @test */
    public function exceptions_that_implement_user_facing_will_be_used_to_get_the_title_and_details()
    {
        $provider = $this->newProvider([
            401 => ['title' => 'Unauthorized', 'details' => 'You need to log-in first.'],
            403 => ['title' => 'Forbidden', 'details' => 'You cant do this.'],
        ]);
        
        $e = new UserFacingException('Secret stuff here');
        
        $information = $provider->provideFor($e);
        
        $this->assertSame(500, $information->statusCode());
        $this->assertSame($e, $information->originalException());
        $this->assertSame('Foo title', $information->title());
        $this->assertSame('Bar details', $information->safeDetails());
    }
    
    /** @test */
    public function user_facing_exceptions_will_be_used_even_if_a_transformer_transforms_them()
    {
        $provider = $this->newProvider([
            403 => ['title' => 'Forbidden', 'details' => 'You cant do this.'],
        ], new StubIdentifier('foobar_id'), new TransformEverythingTo403());
        
        $e = new UserFacingException('Secret stuff here');
        
        $information = $provider->provideFor($e);
        
        $this->assertSame(403, $information->statusCode());
        $this->assertSame('foobar_id', $information->identifier());
        $this->assertSame($e, $information->originalException());
        $this->assertSame('Foo title', $information->title());
        $this->assertSame('Bar details', $information->safeDetails());
    }
    
    /** @test */
    public function a_transformed_user_facing_exception_has_priority_over_a_provided_user_facing_exception()
    {
        $provider =
            $this->newProvider([], new StubIdentifier('foo_id'), new ToUserFacingException());
        
        $e = new UserFacingException('Secret stuff here');
        
        $information = $provider->provideFor($e);
        
        $this->assertSame(500, $information->statusCode());
        $this->assertSame('foo_id', $information->identifier());
        $this->assertSame($e, $information->originalException());
        $this->assertSame('transformed_user_facing_title', $information->title());
        $this->assertSame('transformed_user_facing_details', $information->safeDetails());
    }
    
    private function newProvider(array $data = [], ExceptionIdentifier $identifier = null, ExceptionTransformer ...$transformer) :HttpInformationProvider
    {
        if ( ! isset($data[500])) {
            $data[500] = [
                'title' => 'Internal Server Error',
                'details' => 'An error has occurred and this resource cannot be displayed.',
            ];
        }
        return new HttpInformationProvider(
            $data,
            $identifier ? : new SplHashIdentifier(),
            ...
            $transformer
        );
    }
    
}

class StubIdentifier implements ExceptionIdentifier
{
    
    private string $stub_id;
    
    public function __construct(string $stub_id)
    {
        $this->stub_id = $stub_id;
    }
    
    public function identify(Throwable $e) :string
    {
        return $this->stub_id;
    }
    
}

class ToUserFacingException implements ExceptionTransformer
{
    
    public function transform(Throwable $e) :Throwable
    {
        return new TransformedUserFacingException();
    }
    
}

class TransformEverythingTo403 implements ExceptionTransformer
{
    
    public function transform(Throwable $e) :Throwable
    {
        return HttpException::fromPrevious(403, $e);
    }
    
}

class UserFacingException extends RuntimeException implements UserFacing
{
    
    public function title() :string
    {
        return 'Foo title';
    }
    
    public function safeDetails() :string
    {
        return 'Bar details';
    }
    
}

class LastTransformer implements ExceptionTransformer
{
    
    public function transform(Throwable $e) :Throwable
    {
        return HttpException::fromPrevious(403, $e);
    }
    
}

class RuntimeToAuthTransformer implements ExceptionTransformer
{
    
    public function transform(Throwable $e) :Throwable
    {
        if ($e instanceof RuntimeException && 'transform_me' === $e->getMessage()) {
            return HttpException::fromPrevious(401, $e);
        }
        return $e;
    }
    
}

class TransformedUserFacingException extends RuntimeException implements UserFacing
{
    
    public function title() :string
    {
        return 'transformed_user_facing_title';
    }
    
    public function safeDetails() :string
    {
        return 'transformed_user_facing_details';
    }
    
}