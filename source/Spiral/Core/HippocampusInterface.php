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
 *
 * @todo Do we need NeuronInterface [remember(data), forget()] or something similar?
 */
interface HippocampusInterface
{
    /**
     * Read data from long memory cache. Must return exacts same value as saved or null.
     *
     * @param string $section  Non case sensitive.
     * @param string $location Specific memory location.
     *
     * @return string|array|null
     */
    public function loadData(string $section, string $location = null);

    /**
     * Put data to long memory cache. No inner references or closures are allowed.
     *
     * @param string       $section  Non case sensitive.
     * @param string|array $data
     * @param string       $location Specific memory location.
     */
    public function saveData(string $section, $data, string $location = null);
}
