<?php

declare(strict_types=1);

namespace Snicco\Component\Psr7ErrorHandler\Filter;

use Psr\Http\Message\RequestInterface;
use Snicco\Component\Psr7ErrorHandler\Displayer;
use Snicco\Component\Psr7ErrorHandler\DisplayerFilter;
use Snicco\Component\Psr7ErrorHandler\Information\ExceptionInformation;

use function array_filter;

/**
 * @note This Filter assumes that content negotiation already happened and that the request has the
 *       best negotiated content type filter already set. @see
 *      {https://github.com/middlewares/negotiation/blob/master/src/ContentType.php#L156}
 * @api
 */
final class ContentTypeFilter implements DisplayerFilter
{
    
    public function filter(array $displayers, RequestInterface $request, ExceptionInformation $info) :array
    {
        return array_filter($displayers, function (Displayer $displayer) use ($request) {
            return $this->matchingContentTypes($request, $displayer);
        });
    }
    
    private function matchingContentTypes(RequestInterface $request, Displayer $displayer) :bool
    {
        $accept = $request->getHeaderLine('Accept');
        return $accept === $displayer->supportedContentType();
    }
    
}