<?php

namespace Maatwebsite\Sidebar\Traits;

trait CacheableTrait
{
    /**
     * Return the properties that should be included when the object is cached.
     */
    public function __serialize(): array
    {
        $data = [];

        foreach ($this->getCacheables() as $cacheable) {
            $data[$cacheable] = $this->{$cacheable};
        }

        return $data;
    }

    /**
     * Restore the properties that were included in the cached object.
     */
    public function __unserialize(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }
    }

    /**
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     */
    public function serialize()
    {
        return serialize($this->__serialize());
    }

    /**
     * Constructs the object
     * @link http://php.net/manual/en/serializable.unserialize.php
     *
     * @param string $serialized The string representation of the object.
     *
     * @return void
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized);
        $this->__unserialize($data);
    }

    /**
     * @return array
     */
    public function getCacheables(): array
    {
        return $this->cacheables ?? ['menu'];
    }
}
