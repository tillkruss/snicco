<?php

declare(strict_types=1);

namespace Snicco\EventDispatcher\Contracts;

/**
 * @api
 */
interface EventParser
{
    
    /**
     * @param  string|Event  $event
     * @param  array  $payload
     *
     * @return array<string,Event>
     */
    public function transformEventNameAndPayload($event, array $payload) :array;
    
}