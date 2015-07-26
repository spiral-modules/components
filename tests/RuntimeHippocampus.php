<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Tests;

use Spiral\Core\HippocampusInterface;

class RuntimeHippocampus implements HippocampusInterface
{
    /**
     * Data to be stored or loaded.s
     *
     * @var array
     */
    protected $data = [];

    /**
     * Read data from long memory cache. Will return null if no data presented.
     *
     * @param string $name
     * @param string $location Specific memory location.
     * @return mixed|array
     */
    public function loadData($name, $location = null)
    {
        if (!isset($this->data[$location . $name]))
        {
            return null;
        }

        return $this->data[$location . $name];
    }

    /**
     * Put data to long memory cache.
     *
     * @param string $name
     * @param mixed  $data
     * @param string $location Specific memory location.
     */
    public function saveData($name, $data, $location = null)
    {
        $this->data[$location . $name] = $data;
    }
}