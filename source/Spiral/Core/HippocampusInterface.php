<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core;

interface HippocampusInterface
{
    /**
     * Read data from long memory cache. Will return null if no data presented.
     *
     * @param string $name
     * @param string $location Specific memory location.
     * @return mixed|array
     */
    public function loadData($name, $location = null);

    /**
     * Put data to long memory cache.
     *
     * @param string $name
     * @param mixed  $data
     * @param string $location Specific memory location.
     */
    public function saveData($name, $data, $location = null);
}