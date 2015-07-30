<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Core;

/**
 * Long memory cache. Something very fast on read and slow on write!
 */
interface HippocampusInterface
{
    /**
     * Read data from long memory cache. Will return null if no data presented.
     *
     * @param string $name
     * @param string $location Specific memory location.
     * @return mixed|null
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