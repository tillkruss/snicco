<?php

declare(strict_types=1);

namespace Snicco\Component\Psr7ErrorHandler\Filter;

use Psr\Http\Message\RequestInterface;
use Snicco\Component\Psr7ErrorHandler\DisplayerFilter;
use Snicco\Component\Psr7ErrorHandler\Information\ExceptionInformation;

/**
 * @api
 */
final class MultipleFilter implements DisplayerFilter
{
    
    /**
     * @var DisplayerFilter[]
     */
    private array $filters;
    
    public function __construct(DisplayerFilter ...$filter)
    {
        $this->filters = $filter;
    }
    
    public function filter(array $displayers, RequestInterface $request, ExceptionInformation $info) :array
    {
        foreach ($this->filters as $filter) {
            $displayers = $filter->filter($displayers, $request, $info);
        }
        return $displayers;
    }
    
}