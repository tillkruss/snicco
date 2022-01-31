<?php

declare(strict_types=1);

namespace Snicco\Component\HttpRouting\Routing\Exception;

use RuntimeException;

final class RouteNotFound extends RuntimeException
{
    
    public static function name(string $name) :RouteNotFound
    {
        return new self("There is no route with name [$name].");
    }
    
    public static function accessByBadName(string $used_name, string $real_name) :RouteNotFound
    {
        return new self(
            "Route accessed with bad name.\nRoute with real name [$real_name] is stored with name [$used_name]."
        );
    }
    
}