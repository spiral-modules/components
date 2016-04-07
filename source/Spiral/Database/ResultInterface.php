<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Database;

use Spiral\Database\Exceptions\ResultException;

/**
 * Must represent single query result.
 *
 * Compatible with PDOStatement and decorates it in PDOResult.
 */
interface ResultInterface extends \Traversable, \Countable
{
    /**
     * The number of columns in the result set.
     *
     * @return int
     */
    public function countColumns();

    /**
     * Fetch one result row as array or return false.
     *
     * @return array|bool
     */
    public function fetch();

    /**
     * Fetch given class instance. Behaviour must be similar to Doctrine\Instantiator and must
     * support AbstractEntity classes initiation using constructor.
     *
     * @param string $class
     * @return object|null
     *
     * @throws ResultException
     */
    public function fetchInstance($class);

    /**
     * Returns an array containing all of the result set rows. Avoid using this method
     *
     * @return array
     */
    public function fetchAll();

    /**
     * Close result iterator. Must free as much memory as it can.
     */
    public function close();
}
