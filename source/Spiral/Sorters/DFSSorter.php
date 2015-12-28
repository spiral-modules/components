<?php

namespace Spiral\Sorters;

/**
 * Topological Sorting vs Depth First Traversal (DFS)
 * https://en.wikipedia.org/wiki/Topological_sorting
 *
 * Class DFSSorter
 * @package Spiral\Sorters
 */
class DFSSorter
{
    const COLOR_GRAY = 1;
    const COLOR_BLACK = 2;

    /** @var array */
    protected $colors = [];

    /** @var array mixed[] */
    protected $stack = [];

    /** @var array mixed[] */
    protected $objects = [];

    /** @var array mixed[] */
    protected $dependencies = [];

    /** @var array string[] */
    protected $keys = [];

    /**
     * @param string $key
     * @param mixed $object
     * @param array $dependencies
     */
    public function addItemObject($key, $object, array $dependencies)
    {
        $this->objects[$key] = $object;
        $this->dependencies[$key] = $dependencies;
        $this->keys[] = $key;
    }

    /**
     * @param string $key
     * @param array $dependencies
     */
    private function dfs($key, array $dependencies)
    {
        if (isset($this->colors[$key])) {
            return;
        }

        $this->colors[$key] = self::COLOR_GRAY;
        foreach ($dependencies as $dependency) {
            $this->dfs($dependency, $this->dependencies[$dependency]);
        }
        $this->stack[] = $this->objects[$key];
        $this->colors[$key] = self::COLOR_BLACK;

        return;
    }

    /**
     * @return array
     */
    public function sort()
    {
        $items = array_values($this->keys);

        foreach ($items as $item) {
            $this->dfs($item, $this->dependencies[$item]);
        }

        return $this->stack;
    }

    /**
     * @return array
     */
    public function getColors()
    {
        return $this->colors;
    }
}