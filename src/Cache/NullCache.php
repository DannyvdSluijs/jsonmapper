<?php

namespace JsonMapper\Cache;

use Psr\SimpleCache\CacheInterface;

class NullCache implements CacheInterface
{
    public function get($key, $default = null)
    {
        return $default;
    }

    public function set($key, $value, $ttl = null)
    {
        return;
    }

    public function delete($key)
    {
        return;
    }

    public function clear()
    {
        return;
    }

    public function getMultiple($keys, $default = null)
    {
        $values = [];
        array_walk($keys, function($key) use ($default, &$values) {
            $values[$key] = $default;
        });

        return $values;
    }

    public function setMultiple($values, $ttl = null)
    {
        return;
    }

    public function deleteMultiple($keys)
    {
        return;
    }

    public function has($key)
    {
        return false;
    }
}