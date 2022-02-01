<?php

declare(strict_types=1);

namespace Snicco\Component\ParameterBag;

use ArrayAccess;
use ReturnTypeWillChange;
use Snicco\Component\StrArr\Arr;

/**
 * @api
 */
final class ParameterPag implements ArrayAccess
{

    private array $items;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * @param array<string,mixed> $items
     */
    public function add(array $items): void
    {
        foreach ($items as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * @param mixed $value
     */
    public function set(string $key, $value): void
    {
        Arr::set($this->items, $key, $value);
    }

    public function prepend(string $key, $value): void
    {
        $array = $this->get($key);

        array_unshift($array, $value);

        $this->set($key, $array);
    }

    /**
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return Arr::get($this->items, $key, $default);
    }

    public function append(string $key, $value): void
    {
        $array = $this->get($key);

        $array[] = $value;

        $this->set($key, $array);
    }

    public function toArray(): array
    {
        return $this->items;
    }

    /**
     * @param string $offset
     *
     * @return mixed
     */
    #[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * @param string $offset
     */
    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    public function has(string $key): bool
    {
        return Arr::has($this->items, $key);
    }

    /**
     * @param string $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        $this->set($offset, $value);
    }

    /**
     * @param string $offset
     */
    public function offsetUnset($offset): void
    {
        $this->remove($offset);
    }

    public function remove(string $key): void
    {
        Arr::forget($this->items, $key);
    }

}
