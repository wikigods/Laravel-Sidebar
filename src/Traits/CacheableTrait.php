<?php

namespace Maatwebsite\Sidebar\Traits;

trait CacheableTrait
{
    /**
     * @return array
     */
    public function __serialize(): array
    {
        $cacheables = [];
        foreach ($this->getCacheables() as $cacheable) {
            if (property_exists($this, $cacheable)) {
                $cacheables[$cacheable] = $this->{$cacheable};
            }
        }

        return $cacheables;
    }

    /**
     * @param array $data
     */
    public function __unserialize(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }

        if (property_exists($this, 'container')) {
            $this->container = \Illuminate\Container\Container::getInstance();
        }
    }

    /**
     * @return array
     */
    public function getCacheables(): array
    {
        return $this->cacheables ?? [];
    }
}
