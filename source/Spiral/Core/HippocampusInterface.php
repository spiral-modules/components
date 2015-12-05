<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
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
     * @param string $section  Non case sensitive.
     * @param string $location Specific memory location.
     * @return string|array|null
     */
    public function loadData($section, $location = null);

    /**
     * Put data to long memory cache. No inner references or closures are allowed.
     *
     * @param string       $section  Non case sensitive.
     * @param string|array $data
     * @param string       $location Specific memory location.
     */
    public function saveData($section, $data, $location = null);

    /**
     * Get all memory sections belongs to given memory location (default location to be used if
     * none specified).
     *
     * @param string $location
     * @return array
     */
    public function getSections($location = null);
}