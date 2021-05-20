<?php


    declare(strict_types = 1);


    namespace WPEmerge\Traits;

    use WPEmerge\Routing\Route;
    use WPEmerge\Support\Str;

    trait DeserializesRoutes
    {

        private function unserializeAction( Route $route ) : Route
        {

            if ($this->isSerializedClosure($route->getAction())) {

                $action = \Opis\Closure\unserialize($route->getAction());

                $route->handle($action);

            }

            return $route;

        }

        private function isSerializedClosure($action) : bool
        {

            return is_string($action)
                && Str::startsWith($action, 'C:32:"Opis\\Closure\\SerializableClosure') !== false;
        }

    }