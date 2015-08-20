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
     * Read data from long memory cache. Must return exacts same value as saved or null.
     *
     * @param string $name
     * @param string $location Specific memory location.
     * @return string|array|null
     */
    public function loadData($name, $location = null);

    /**
     * Put data to long memory cache. No inner references or closures are allowed.
     *
     * @param string       $name
     * @param string|array $data
     * @param string       $location Specific memory location.
     */
    public function saveData($name, $data, $location = null);
}