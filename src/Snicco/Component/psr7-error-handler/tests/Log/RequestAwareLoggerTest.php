<?php

declare(strict_types=1);

namespace Snicco\Component\Psr7ErrorHandler\Tests\Log;

use TypeError;
use RuntimeException;
use Psr\Log\LogLevel;
use Psr\Log\Test\TestLogger;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Snicco\Component\Psr7ErrorHandler\HttpException;
use Snicco\Component\Psr7ErrorHandler\Log\RequestContext;
use Snicco\Component\Psr7ErrorHandler\Log\RequestAwareLogger;
use Snicco\Component\Psr7ErrorHandler\Information\ExceptionInformation;

final class RequestAwareLoggerTest extends TestCase
{
    
    private ServerRequestInterface $request;
    private TestLogger             $test_logger;
    
    protected function setUp() :void
    {
        parent::setUp();
        $this->request = new ServerRequest('GET', '/foo');
        $this->test_logger = new TestLogger();
    }
    
    /** @test */
    public function exception_information_is_logged()
    {
        $logger = new RequestAwareLogger($test_logger = new TestLogger());
        
        $info = new ExceptionInformation(
            403,
            'foo_id',
            'title',
            'safe_details',
            $e = new RuntimeException('secret stuff here'),
            HttpException::fromPrevious(403, $e)
        );
        
        $logger->log($info, $this->request);
        
        $this->assertTrue($test_logger->hasErrorRecords());
    }
    
    /** @test */
    public function the_exception_and_identifier_is_included_in_the_log_context()
    {
        $logger = new RequestAwareLogger($test_logger = new TestLogger());
        
        $info = new ExceptionInformation(
            403,
            'foo_id',
            'title',
            'safe_details',
            $e = new RuntimeException('secret stuff here'),
            HttpException::fromPrevious(403, $e)
        );
        
        $logger->log($info, $this->request);
        
        $this->assertTrue(
            $test_logger->hasError([
                'message' => 'secret stuff here',
                'context' => [
                    'exception' => $e,
                    'identifier' => 'foo_id',
                ],
            ])
        );
    }
    
    /** @test */
    public function errors_are_logged_as_critical_by_default()
    {
        $logger = new RequestAwareLogger($test_logger = new TestLogger());
        
        $info = new ExceptionInformation(
            403,
            'foo_id',
            'title',
            'safe_details',
            $e = new TypeError('secret stuff here'),
            HttpException::fromPrevious(403, $e)
        );
        
        $logger->log($info, $this->request);
        
        $this->assertTrue(
            $test_logger->hasCritical([
                'message' => 'secret stuff here',
                'context' => [
                    'exception' => $e,
                    'identifier' => 'foo_id',
                ],
            ])
        );
    }
    
    /** @test */
    public function custom_log_levels_can_be_provided()
    {
        $logger = new RequestAwareLogger(
            $this->test_logger,
            [
                RuntimeException::class => LogLevel::WARNING,
            ]
        );
        
        $info = new ExceptionInformation(
            403,
            'foo_id',
            'title',
            'safe_details',
            $e = new RuntimeException('secret stuff here'),
            HttpException::fromPrevious(403, $e)
        );
        
        $logger->log($info, $this->request);
        
        $this->assertFalse($this->test_logger->hasErrorRecords());
        $this->assertTrue($this->test_logger->hasWarningRecords());
        
        $this->test_logger->reset();
        
        $info = new ExceptionInformation(
            403,
            'foo_id',
            'title',
            'safe_details',
            $e = new InvalidArgumentException('secret stuff here'),
            HttpException::fromPrevious(403, $e)
        );
        
        $logger->log($info, $this->request);
        
        $this->assertFalse($this->test_logger->hasWarningRecords());
        $this->assertTrue($this->test_logger->hasErrorRecords());
    }
    
    /** @test */
    public function request_context_can_be_added_to_the_log_entry()
    {
        $logger = new RequestAwareLogger(
            $this->test_logger,
            [],
            new PathContext(),
            new MethodContext(),
        );
        
        $info = new ExceptionInformation(
            403,
            'foo_id',
            'title',
            'safe_details',
            $e = new RuntimeException('secret stuff here'),
            HttpException::fromPrevious(403, $e)
        );
        
        $logger->log($info, $this->request);
        
        $this->assertTrue(
            $this->test_logger->hasError([
                'message' => 'secret stuff here',
                'context' => [
                    'exception' => $e,
                    'identifier' => 'foo_id',
                    'path' => $this->request->getUri()->getPath(),
                    'method' => $this->request->getMethod(),
                ],
            ])
        );
    }
    
}

class PathContext implements RequestContext
{
    
    public function add(array $context, RequestInterface $request) :array
    {
        $context['path'] = $request->getUri()->getPath();
        return $context;
    }
    
}

class MethodContext implements RequestContext
{
    
    public function add(array $context, RequestInterface $request) :array
    {
        $context['method'] = $request->getMethod();
        return $context;
    }
    
}